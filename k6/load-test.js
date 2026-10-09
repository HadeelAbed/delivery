import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

/*
 * Load-test suite for REQ-17 (SPEC-001):
 *   - order placement + order-state propagation (target: within 1-3 s)
 *   - tracking polling at the intended 7 s cadence
 *   - mixed checkout + offline-sync load
 *
 * Runs OUTSIDE PHPUnit by design (SPEC-001 §2 performance note).
 * See k6/README.md for prerequisites, seed-data prep and full docs.
 */

// ---------------------------------------------------------------- safety --
const BASE_URL = (__ENV.BASE_URL || '').replace(/\/+$/, '');
if (!BASE_URL) {
  throw new Error(
    '[safety] BASE_URL is required. Example: --env BASE_URL=http://127.0.0.1:8000 ' +
    '(local or staging only; never point this at production).'
  );
}

const isLocalhost = /^https?:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/.test(BASE_URL);
if (!isLocalhost && __ENV.ALLOW_NONLOCAL_BASE_URL !== 'true') {
  throw new Error(
    '[safety] BASE_URL is not localhost. Non-local targets require an explicit ' +
    '--env ALLOW_NONLOCAL_BASE_URL=true (staging only; never production).'
  );
}

const CUSTOMER_EMAIL = __ENV.CUSTOMER_EMAIL || '';
const CUSTOMER_PASSWORD = __ENV.CUSTOMER_PASSWORD || '';
const CUSTOMER_API_TOKEN = __ENV.CUSTOMER_API_TOKEN || '';
const DRIVER_API_TOKEN = __ENV.DRIVER_API_TOKEN || '';
const TRACKING_ORDER_ID = __ENV.TRACKING_ORDER_ID || '';
const SYNC_ORDER_ID = __ENV.SYNC_ORDER_ID || '';

if (!CUSTOMER_EMAIL || !CUSTOMER_PASSWORD || !CUSTOMER_API_TOKEN) {
  throw new Error(
    '[config] CUSTOMER_EMAIL, CUSTOMER_PASSWORD and CUSTOMER_API_TOKEN are required. ' +
    'See "Seed-data setup" in k6/README.md. Credentials are passed via --env only; ' +
    'none are hardcoded in this script.'
  );
}

// --------------------------------------------------------------- metrics --
const orderPropagation = new Trend('order_propagation_ms', true);
const checkoutPlacement = new Trend('checkout_placement_ms', true);
const pollInterval = new Trend('poll_interval_ms', true);
const trackingFreshRate = new Rate('tracking_fresh_rate');
const syncAcceptedRate = new Rate('sync_accepted_rate');

// -------------------------------------------------------------- scenarios --
// All bounded by design: max 13 concurrent VUs, <= 3 minutes total.
const scenarios = {
  order_placement: {
    executor: 'per-vu-iterations',
    exec: 'orderPlacement',
    vus: 4,
    iterations: 8,
    maxDuration: '3m',
    tags: { stage: 'placement' },
  },
  mixed_checkout: {
    executor: 'ramping-vus',
    exec: 'mixedCheckout',
    startVUs: 0,
    stages: [
      { duration: '30s', target: 4 },
      { duration: '1m', target: 4 },
      { duration: '30s', target: 0 },
    ],
    maxDuration: '2m30s',
    tags: { stage: 'mixed' },
  },
};

if (TRACKING_ORDER_ID) {
  scenarios.tracking_poll = {
    executor: 'constant-vus',
    exec: 'trackingPoll',
    vus: 2,
    duration: '2m',
    tags: { stage: 'tracking' },
  };
} else {
  console.warn('TRACKING_ORDER_ID not set - tracking_poll scenario skipped (see README prep).');
}

if (DRIVER_API_TOKEN && SYNC_ORDER_ID) {
  scenarios.mixed_sync = {
    executor: 'ramping-vus',
    exec: 'mixedSync',
    startVUs: 0,
    stages: [
      { duration: '30s', target: 3 },
      { duration: '1m', target: 3 },
      { duration: '30s', target: 0 },
    ],
    maxDuration: '2m30s',
    tags: { stage: 'mixed' },
  };
} else {
  console.warn('DRIVER_API_TOKEN/SYNC_ORDER_ID not set - mixed_sync scenario skipped (see README prep).');
}

export const options = {
  scenarios,
  thresholds: {
    // Overall error budget: < 1% of all requests may fail.
    'http_req_failed': ['rate<0.01'],
    // REQ-17: order/status reflected within 1-3 s -> p95 under 3 s.
    'http_req_duration{stage:placement}': ['p(95)<3000'],
    'http_req_duration{stage:mixed}': ['p(95)<3000'],
    // Tracking poll server processing stays snappy (cadence itself is
    // measured separately via poll_interval_ms below).
    'http_req_duration{stage:tracking}': ['p(95)<1500'],
    // Time from checkout POST start until the new order is visible via
    // GET /api/v1/orders (shared service layer read path).
    order_propagation_ms: ['p(95)<3000'],
    // Cart -> checkout POST round trip.
    checkout_placement_ms: ['p(95)<3000'],
    // Intended cadence: sleep(7s) + request time; p90 must stay < 8.5 s.
    poll_interval_ms: ['p(90)<8500'],
    // Reported but NOT thresholded: a static seed location goes stale by
    // design after 10 s without a live driver (interpretation in README).
    tracking_fresh_rate: [],
    sync_accepted_rate: [],
  },
};

// --------------------------------------------------------------- helpers --
// Per-VU state (each VU has its own JS runtime; module vars persist across
// that VU's iterations). Login happens at most once per VU.
let session = null;

function extractCsrf(html) {
  const m = html.match(/name="_token"\s+value="([^"]+)"/);
  return m ? m[1] : null;
}

function login() {
  const loginPage = http.get(`${BASE_URL}/login`, { tags: { stage: 'auth' } });
  const token = extractCsrf(loginPage.body || '');
  if (!token) {
    throw new Error('CSRF token not found on /login - is the app reachable at BASE_URL?');
  }

  const res = http.post(
    `${BASE_URL}/login`,
    { _token: token, email: CUSTOMER_EMAIL, password: CUSTOMER_PASSWORD },
    { redirects: 5, tags: { stage: 'auth' } }
  );

  check(res, {
    'login succeeded': (r) => r.status === 200 && !String(r.body).includes('These credentials do not match'),
  });

  session = { token };
  return session;
}

function ensureLogin() {
  if (!session) login();
  return session;
}

// Refresh the CSRF token when Laravel rotates it (HTTP 419).
function getToken(pageBody) {
  return extractCsrf(pageBody || '') || (session && session.token);
}

function authHeaders() {
  return {
    Authorization: `Bearer ${CUSTOMER_API_TOKEN}`,
    Accept: 'application/json',
  };
}

function apiOrderTotal() {
  const res = http.get(`${BASE_URL}/api/v1/orders`, { headers: authHeaders(), tags: { stage: 'api' } });
  if (res.status !== 200) return -1;
  try {
    const body = res.json();
    return typeof body.total === 'number' ? body.total : (body.data || []).length;
  } catch (e) {
    return -1;
  }
}

/**
 * One bounded checkout iteration: browse -> product -> cart -> checkout,
 * measuring placement latency and write->read propagation via the API.
 */
function placeOneOrder() {
  ensureLogin();

  const browse = http.get(`${BASE_URL}/customer/browse`);
  const merchantMatch = (browse.body || '').match(/\/customer\/merchants\/(\d+)/);
  check(browse, { 'browse 200': (r) => r.status === 200, 'merchant link found': () => !!merchantMatch });
  if (!merchantMatch) return;

  const merchantPage = http.get(`${BASE_URL}/customer/merchants/${merchantMatch[1]}`);
  const productMatch = (merchantPage.body || '').match(/name="product_id"\s+value="(\d+)"/);
  check(merchantPage, { 'merchant page 200': (r) => r.status === 200, 'product found': () => !!productMatch });
  if (!productMatch) return;

  let token = getToken(merchantPage.body);

  const addRes = http.post(`${BASE_URL}/customer/cart/add`, {
    _token: token,
    product_id: productMatch[1],
    quantity: '1',
  }, { redirects: 5 });
  check(addRes, { 'cart add ok': (r) => r.status === 200 });

  // Fresh CSRF in case the cart POST rotated the token.
  token = getToken(addRes.body) || token;

  const baseline = apiOrderTotal();
  const started = Date.now();

  const checkoutRes = http.post(`${BASE_URL}/customer/checkout`, {
    _token: token,
    'address[label]': 'Load Test, Gaza',
    'address[lat]': '31.5',
    'address[lng]': '34.47',
    payment_method: 'cod',
  }, { redirects: 5 });

  const placementMs = Date.now() - started;
  checkoutPlacement.add(placementMs);

  let status = checkoutRes.status;
  if (status === 419) {
    // CSRF rotated: refresh token from the checkout page and retry once.
    const retryPage = http.get(`${BASE_URL}/customer/checkout`);
    token = getToken(retryPage.body) || token;
    const retry = http.post(`${BASE_URL}/customer/checkout`, {
      _token: token,
      'address[label]': 'Load Test, Gaza',
      'address[lat]': '31.5',
      'address[lng]': '34.47',
      payment_method: 'cod',
    }, { redirects: 5 });
    status = retry.status;
  }

  check(checkoutRes, { 'checkout accepted (not 4xx/5xx)': (r) => r.status >= 200 && r.status < 400 });

  // Propagation: poll the read path until the new order is visible.
  let propagationMs = 6000; // capped failure value so the threshold fails honestly
  let visible = false;
  for (let i = 0; i < 25 && !visible; i++) {
    const total = apiOrderTotal();
    if (baseline >= 0 && total > baseline) {
      visible = true;
      propagationMs = Date.now() - started;
      break;
    }
    sleep(0.2);
  }
  orderPropagation.add(propagationMs);
  check({ visible }, { 'order visible via API (propagation)': (v) => v.visible });
}

// --------------------------------------------------- exported scenarios --

/** Scenario 1a: concurrent order placement + propagation measurement. */
export function orderPlacement() {
  placeOneOrder();
}

/** Scenario 3a: checkout half of the mixed load. */
export function mixedCheckout() {
  placeOneOrder();
}

/** Scenario 2: tracking polling at the intended 7-second cadence. */
export function trackingPoll() {
  if (!TRACKING_ORDER_ID) return;
  ensureLogin();

  const now = Date.now();
  if (trackingPoll._last && trackingPoll._last > 0) {
    pollInterval.add(now - trackingPoll._last);
  }

  const res = http.get(
    `${BASE_URL}/customer/orders/${TRACKING_ORDER_ID}/tracking`,
    { headers: { Accept: 'application/json' }, tags: { stage: 'tracking' } }
  );

  const ok = check(res, { 'tracking poll 200': (r) => r.status === 200 });
  if (ok) {
    try {
      const data = res.json();
      trackingFreshRate.add(data.driver_location && data.driver_location.fresh ? 1 : 0);
    } catch (e) {
      trackingFreshRate.add(0);
    }
  } else {
    trackingFreshRate.add(0);
  }

  trackingPoll._last = Date.now();
  sleep(7); // intended cadence: 7 s between polls (REQ-17: 5-10 s)
}

/** Scenario 3b: offline-sync half of the mixed load (non-mutating). */
export function mixedSync() {
  if (!DRIVER_API_TOKEN || !SYNC_ORDER_ID) return;

  const action = {
    client_uuid: `k6-loadtest-${__VU}-${__ITER}`,
    type: 'delivered',
    payload: { order_id: Number(SYNC_ORDER_ID) },
    at: new Date().toISOString(),
  };

  const res = http.post(
    `${BASE_URL}/api/sync`,
    JSON.stringify({ actions: [action] }),
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${DRIVER_API_TOKEN}`,
      },
      tags: { stage: 'mixed' },
    }
  );

  // 200 expected: the demo order is already terminal, so the state machine
  // answers with an invalid_transition conflict (ack/conflicts payload).
  const accepted = res.status === 200;
  syncAcceptedRate.add(accepted);
  check(res, { 'sync answered 200': (r) => r.status === 200 });
}



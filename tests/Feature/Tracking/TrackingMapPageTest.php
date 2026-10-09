<?php

namespace Tests\Feature\Tracking;

use App\Models\Delivery;
use App\Models\DriverLocation;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrackingMapPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOutForDeliveryOrder(): array
    {
        $customer = User::factory()->create();
        $merchantUser = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create([
            'user_id' => $merchantUser->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
            'latitude' => 31.52,
            'longitude' => 34.45,
        ]);
        $driver = User::factory()->driver()->create(['status' => 'active']);

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchantUser->id,
            'status' => 'out_for_delivery',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'out_for_delivery',
        ]);

        DriverLocation::create([
            'driver_id' => $driver->id,
            'order_id' => $order->id,
            'latitude' => 31.51,
            'longitude' => 34.48,
        ]);

        return [$order, $customer, $merchantUser, $driver];
    }

    private function configureMaps(): void
    {
        Config::set('maps.server_key', 'test-server-key');
        Config::set('maps.base_url', 'https://maps.googleapis.com');
        Config::set('maps.timeout', 5);
        Config::set('maps.cache_ttl', 60);
        Cache::flush();
    }

    private function fakeDirections(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'routes' => [
                [
                    'legs' => [
                        [
                            'distance' => ['value' => 1500],
                            'duration' => ['value' => 300],
                        ],
                    ],
                ],
            ],
        ])]);
    }

    public function test_page_renders_text_fallback_without_browser_key(): void
    {
        Config::set('maps.browser_key', null);
        Config::set('maps.server_key', 'SERVER-SECRET-KEY');
        [$order, $customer] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($customer)->get(route('customer.orders.track', $order));

        $response->assertOk();
        $response->assertSee('data-has-browser-key="0"', false);
        $response->assertSee('id="map"', false);
        $response->assertDontSee('maps.googleapis.com/maps/api/js', false);
        $response->assertDontSee('SERVER-SECRET-KEY', false);
        $response->assertSee(route('customer.orders.tracking', $order), false);
    }

    public function test_page_includes_maps_script_and_pins_with_browser_key(): void
    {
        Config::set('maps.browser_key', 'PUBLIC-BROWSER-KEY');
        Config::set('maps.server_key', 'SERVER-SECRET-KEY');
        [$order, $customer] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($customer)->get(route('customer.orders.track', $order));

        $response->assertOk();
        $response->assertSee('data-has-browser-key="1"', false);
        $response->assertSee('maps.googleapis.com/maps/api/js?key=PUBLIC-BROWSER-KEY', false);
        $response->assertSee('__updateDriverMarker', false);
        $response->assertDontSee('SERVER-SECRET-KEY', false);
        $response->assertSee('31.5', false);
        $response->assertSee('34.47', false);
    }

    public function test_json_poll_never_exposes_server_key(): void
    {
        Config::set('maps.server_key', 'SERVER-SECRET-KEY');
        Http::fake();
        [$order, $customer] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($customer)->get(route('customer.orders.tracking', $order));

        $response->assertOk();
        $response->assertJsonMissingPath('server_key');
        $this->assertStringNotContainsString('SERVER-SECRET-KEY', (string) $response->getContent());
    }

    public function test_page_authorization_mirrors_json_rules(): void
    {
        Config::set('maps.browser_key', null);
        [$order, , $merchantUser] = $this->makeOutForDeliveryOrder();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('customer.orders.track', $order))->assertForbidden();
        $this->actingAs($merchantUser)->get(route('customer.orders.track', $order))->assertForbidden();

        $order->update(['status' => 'delivered']);
        $customer = $order->customer;
        $this->actingAs($customer)->get(route('customer.orders.track', $order))->assertNotFound();
    }

    public function test_json_route_estimate_success_with_mocked_provider(): void
    {
        $this->configureMaps();
        $this->fakeDirections();
        [$order, $customer] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($customer)->get(route('customer.orders.tracking', $order));

        $response->assertOk();
        $response->assertJsonPath('route.distance_km', 1.5);
        $response->assertJsonPath('route.duration_seconds', 300);
        $response->assertJsonPath('route.eta_text', '5 min');
        $response->assertJsonPath('driver_location.latitude', 31.51);
    }

    public function test_json_route_estimate_null_when_provider_fails(): void
    {
        $this->configureMaps();
        Http::fake(['maps.googleapis.com/*' => Http::response('err', 503)]);
        [$order, $customer] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($customer)->get(route('customer.orders.tracking', $order));

        $response->assertOk();
        $response->assertJsonPath('route', null);
        $response->assertJsonPath('driver_location.fresh', true);
    }

    public function test_json_route_estimate_null_without_server_key(): void
    {
        Config::set('maps.server_key', null);
        Http::fake();
        [$order, $customer] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($customer)->get(route('customer.orders.tracking', $order));

        $response->assertOk();
        $response->assertJsonPath('route', null);
        Http::assertNothingSent();
    }

    public function test_json_route_estimate_null_when_destination_coordinates_missing(): void
    {
        $this->configureMaps();
        $this->fakeDirections();
        [$order, $customer] = $this->makeOutForDeliveryOrder();
        $order->update(['delivery_address' => ['label' => 'No coords']]);

        $response = $this->actingAs($customer)->get(route('customer.orders.tracking', $order));

        $response->assertOk();
        $response->assertJsonPath('route', null);
        Http::assertNothingSent();
    }
}

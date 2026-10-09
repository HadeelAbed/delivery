{{--
    Reusable map surface (REQ-13).

    Expects: $map = ['browser_key' => ?string,
                     'merchant'    => ?{lat,lng,label},
                     'destination' => ?{lat,lng,label}]

    Graceful degradation: without a browser key this renders the plain
    polling placeholder — tracking keeps working exactly as before.

    The SERVER key must NEVER reach this file — only the browser key
    (public, referrer-restricted).
--}}
@php($pins = array_filter([
    'merchant' => $map['merchant'] ?? null,
    'destination' => $map['destination'] ?? null,
], fn ($pin) => is_array($pin)))
<div id="map" data-has-browser-key="{{ ($map['browser_key'] ?? null) ? '1' : '0' }}"
     @if(($map['browser_key'] ?? null)) data-pins="{{ json_encode($pins, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}" @endif
     style="width: 100%; height: 300px; background: #e2e8f0; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; color: #64748b;">
    {{ __('customer.map_loading') }}
</div>

@if($map['browser_key'] ?? null)
    <script>
        window.__trackingMapReady = false;
        window.__updateDriverMarker = null;

        window.__initTrackingMap = function () {
            var el = document.getElementById('map');
            if (!el) return;

            var pins = JSON.parse(el.getAttribute('data-pins') || '{}');
            var center = pins.destination || pins.merchant || { lat: 31.5, lng: 34.47 };

            var map = new google.maps.Map(el, {
                center: { lat: center.lat, lng: center.lng },
                zoom: 13,
                mapTypeControl: false,
                streetViewControl: false,
            });

            Object.keys(pins).forEach(function (kind) {
                new google.maps.Marker({
                    map: map,
                    position: { lat: pins[kind].lat, lng: pins[kind].lng },
                    label: kind === 'merchant' ? 'M' : 'D',
                    title: pins[kind].label || undefined,
                });
            });

            var driverMarker = null;
            window.__updateDriverMarker = function (lat, lng) {
                var pos = { lat: lat, lng: lng };
                if (!driverMarker) {
                    driverMarker = new google.maps.Marker({
                        map: map,
                        position: pos,
                        label: 'DRV',
                    });
                } else {
                    driverMarker.setPosition(pos);
                }
                map.panTo(pos);
            };

            window.__trackingMapReady = true;
        };
    </script>
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key={{ $map['browser_key'] }}&callback=__initTrackingMap">
    </script>
@endif
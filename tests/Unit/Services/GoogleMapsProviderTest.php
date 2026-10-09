<?php

namespace Tests\Unit\Services;

use App\Contracts\MapPoint;
use App\Contracts\RouteEstimate;
use App\Services\Maps\GoogleMapsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleMapsProviderTest extends TestCase
{
    use RefreshDatabase;

    private function configureProvider(): void
    {
        Config::set('maps.server_key', 'test-server-key');
        Config::set('maps.base_url', 'https://maps.googleapis.com');
        Config::set('maps.timeout', 5);
        Config::set('maps.cache_ttl', 60);
        Cache::flush();
    }

    private function directionsPayload(): array
    {
        return [
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
        ];
    }

    private function origin(): MapPoint
    {
        return new MapPoint(31.51, 34.48);
    }

    private function destination(): MapPoint
    {
        return new MapPoint(31.5, 34.47);
    }

    public function test_successful_routing_returns_distance_and_eta(): void
    {
        $this->configureProvider();
        Http::fake(['maps.googleapis.com/*' => Http::response($this->directionsPayload())]);

        $estimate = (new GoogleMapsProvider)->route($this->origin(), $this->destination());

        $this->assertInstanceOf(RouteEstimate::class, $estimate);
        $this->assertSame(1.5, $estimate->distanceKm);
        $this->assertSame(300, $estimate->durationSeconds);
        $this->assertSame('5 min', $estimate->toArray()['eta_text']);

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), '/maps/api/directions/json')
                && str_contains((string) $request->url(), 'key=test-server-key');
        });
    }

    public function test_missing_server_key_short_circuits_without_http_call(): void
    {
        Config::set('maps.server_key', null);
        Http::fake();

        $estimate = (new GoogleMapsProvider)->route($this->origin(), $this->destination());

        $this->assertNull($estimate);
        Http::assertNothingSent();
        $this->assertFalse((new GoogleMapsProvider)->isConfigured());
    }

    public function test_provider_http_failure_degrades_to_null(): void
    {
        $this->configureProvider();
        Http::fake(['maps.googleapis.com/*' => Http::response('boom', 500)]);

        $estimate = (new GoogleMapsProvider)->route($this->origin(), $this->destination());

        $this->assertNull($estimate);
    }

    public function test_provider_connection_failure_degrades_to_null(): void
    {
        $this->configureProvider();
        Http::fake(function () {
            throw new \RuntimeException('network down');
        });

        $estimate = (new GoogleMapsProvider)->route($this->origin(), $this->destination());

        $this->assertNull($estimate);
    }

    public function test_malformed_provider_payload_degrades_to_null(): void
    {
        $this->configureProvider();
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS', 'routes' => []])]);

        $estimate = (new GoogleMapsProvider)->route($this->origin(), $this->destination());

        $this->assertNull($estimate);
    }

    public function test_invalid_coordinates_are_rejected_without_http_call(): void
    {
        $this->configureProvider();
        Http::fake();

        $this->assertNull((new GoogleMapsProvider)->route(new MapPoint(999.0, 34.47), $this->destination()));
        $this->assertNull((new GoogleMapsProvider)->route($this->origin(), new MapPoint(31.5, -999.0)));
        $this->assertNull((new GoogleMapsProvider)->route(new MapPoint(0.0, 0.0), $this->destination()));

        Http::assertNothingSent();
    }

    public function test_route_results_are_cached(): void
    {
        $this->configureProvider();
        Http::fake(['maps.googleapis.com/*' => Http::response($this->directionsPayload())]);

        $provider = new GoogleMapsProvider;
        $first = $provider->route($this->origin(), $this->destination());
        $second = $provider->route($this->origin(), $this->destination());

        $this->assertNotNull($first);
        $this->assertEquals($first, $second);
        Http::assertSentCount(1);
    }
}

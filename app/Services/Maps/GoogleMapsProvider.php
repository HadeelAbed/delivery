<?php

namespace App\Services\Maps;

use App\Contracts\MapPoint;
use App\Contracts\MapProvider;
use App\Contracts\RouteEstimate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsProvider implements MapProvider
{
    public function isConfigured(): bool
    {
        return (bool) config('maps.server_key');
    }

    public function route(MapPoint $origin, MapPoint $destination): ?RouteEstimate
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if (! $this->validCoordinates($origin) || ! $this->validCoordinates($destination)) {
            return null;
        }

        $cacheKey = sprintf(
            'maps.route.%s.%s.%s.%s',
            $origin->latitude,
            $origin->longitude,
            $destination->latitude,
            $destination->longitude
        );

        try {
            return Cache::remember($cacheKey, now()->addSeconds((int) config('maps.cache_ttl', 60)), function () use ($origin, $destination) {
                return $this->fetchRoute($origin, $destination);
            });
        } catch (\Throwable $e) {
            Log::warning('maps route lookup failed; degrading to no ETA', [
                'origin' => [$origin->latitude, $origin->longitude],
                'destination' => [$destination->latitude, $destination->longitude],
            ]);

            return null;
        }
    }

    private function fetchRoute(MapPoint $origin, MapPoint $destination): ?RouteEstimate
    {
        try {
            $response = Http::baseUrl((string) config('maps.base_url'))
                ->timeout((int) config('maps.timeout', 5))
                ->get('/maps/api/directions/json', [
                    'origin' => $origin->latitude.','.$origin->longitude,
                    'destination' => $destination->latitude.','.$destination->longitude,
                    'key' => config('maps.server_key'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('maps provider request failed', ['error' => class_basename($e)]);

            return null;
        }

        if (! $response->ok()) {
            Log::warning('maps provider returned non-OK status', ['status' => $response->status()]);

            return null;
        }

        $leg = $response->json('routes.0.legs.0');

        if (! is_array($leg)) {
            return null;
        }

        $distanceMeters = $leg['distance']['value'] ?? null;
        $durationSeconds = $leg['duration']['value'] ?? null;

        if (! is_numeric($distanceMeters) || ! is_numeric($durationSeconds)) {
            return null;
        }

        return new RouteEstimate(
            round(((float) $distanceMeters) / 1000, 1),
            (int) $durationSeconds
        );
    }

    private function validCoordinates(MapPoint $point): bool
    {
        return $point->latitude >= -90 && $point->latitude <= 90
            && $point->longitude >= -180 && $point->longitude <= 180
            && ($point->latitude != 0 || $point->longitude != 0);
    }
}

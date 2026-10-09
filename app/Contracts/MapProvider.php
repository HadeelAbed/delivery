<?php

namespace App\Contracts;

interface MapProvider
{
    public function isConfigured(): bool;

    public function route(MapPoint $origin, MapPoint $destination): ?RouteEstimate;
}

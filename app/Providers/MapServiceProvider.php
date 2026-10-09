<?php

namespace App\Providers;

use App\Contracts\MapProvider;
use App\Services\Maps\GoogleMapsProvider;
use Illuminate\Support\ServiceProvider;

class MapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MapProvider::class, GoogleMapsProvider::class);
    }
}

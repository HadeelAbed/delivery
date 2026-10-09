<?php

namespace App\Contracts;

class MapPoint
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $label = null,
    ) {}
}

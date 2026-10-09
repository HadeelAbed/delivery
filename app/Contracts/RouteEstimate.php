<?php

namespace App\Contracts;

class RouteEstimate
{
    public function __construct(
        public readonly ?float $distanceKm,
        public readonly ?int $durationSeconds,
    ) {}

    /**
     * @return array{distance_km: ?float, duration_seconds: ?int, eta_text: ?string}
     */
    public function toArray(): array
    {
        return [
            'distance_km' => $this->distanceKm,
            'duration_seconds' => $this->durationSeconds,
            'eta_text' => $this->durationSeconds === null
                ? null
                : $this->formatDuration($this->durationSeconds),
        ];
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' sec';
        }

        $minutes = (int) round($seconds / 60);

        if ($minutes < 60) {
            return $minutes.' min';
        }

        return (int) floor($minutes / 60).' h '.($minutes % 60).' min';
    }
}

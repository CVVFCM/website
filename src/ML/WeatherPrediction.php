<?php

declare(strict_types=1);

namespace App\ML;

/**
 * Physical output of the weather-correction model for one hour.
 */
final readonly class WeatherPrediction
{
    public function __construct(
        public float $temperature,
        public float $windSpeed,
        public int $windDirection,
    ) {
    }
}

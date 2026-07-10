<?php

declare(strict_types=1);

namespace App\Weather;

/**
 * Signed wind gaps of an observed reading against the forecast and against the ML correction of that
 * same forecast. Any field is null when the corresponding reference is unavailable.
 *
 * Speed gaps are percentages; direction gaps are degrees in (-180, 180].
 */
final readonly class LiveWeatherComparison
{
    public function __construct(
        public ?float $windSpeedGapForecast = null,
        public ?float $windDirectionGapForecast = null,
        public ?float $windSpeedGapModel = null,
        public ?float $windDirectionGapModel = null,
    ) {
    }
}

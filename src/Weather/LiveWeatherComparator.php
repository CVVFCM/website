<?php

declare(strict_types=1);

namespace App\Weather;

use App\DTO\LiveWeather;
use App\Entity\WeatherForecastRecord;
use App\ML\ForecastFeatureExtractor;
use App\ML\WeatherModelInference;

/**
 * Computes how far an observed live reading drifts from (a) the raw forecast and (b) the ML
 * correction of that forecast, for wind speed and direction. Used at live-weather import time to
 * record forecast/model accuracy alongside each observation.
 */
final readonly class LiveWeatherComparator
{
    public function __construct(
        private WeatherModelInference $inference,
    ) {
    }

    public function compare(LiveWeather $observed, ?WeatherForecastRecord $forecast): LiveWeatherComparison
    {
        if (null === $forecast) {
            return new LiveWeatherComparison();
        }

        $speedGapForecast = WindComparison::speedGapPercent($observed->windSpeed, $forecast->windSpeed);
        $directionGapForecast = WindComparison::directionGapDegrees((float) $observed->windDirection, (float) $forecast->windDirection);

        $speedGapModel = null;
        $directionGapModel = null;
        $prediction = $this->inference->tryPredict(ForecastFeatureExtractor::fromRecord($forecast));
        if (null !== $prediction) {
            $speedGapModel = WindComparison::speedGapPercent($observed->windSpeed, $prediction->windSpeed);
            $directionGapModel = WindComparison::directionGapDegrees((float) $observed->windDirection, (float) $prediction->windDirection);
        }

        return new LiveWeatherComparison($speedGapForecast, $directionGapForecast, $speedGapModel, $directionGapModel);
    }
}

<?php

declare(strict_types=1);

namespace App\Weather;

use App\Entity\WeatherForecastRecord;
use App\ML\ForecastFeatureExtractor;
use App\ML\WeatherModelInference;

/**
 * Computes how far an observed wind reading drifts from (a) the raw forecast and (b) the ML
 * correction of that forecast, for wind speed and direction. Used on every live-weather import to
 * record forecast/model accuracy alongside each observation.
 */
final readonly class LiveWeatherComparator
{
    public function __construct(
        private WeatherModelInference $inference,
    ) {
    }

    public function compare(float $observedWindSpeed, int $observedWindDirection, ?WeatherForecastRecord $forecast): LiveWeatherComparison
    {
        if (null === $forecast) {
            return new LiveWeatherComparison();
        }

        $speedGapForecast = WindComparison::speedGapPercent($observedWindSpeed, $forecast->windSpeed);
        $directionGapForecast = WindComparison::directionGapDegrees((float) $observedWindDirection, (float) $forecast->windDirection);

        $speedGapModel = null;
        $directionGapModel = null;
        $prediction = $this->inference->tryPredict(ForecastFeatureExtractor::fromRecord($forecast));
        if (null !== $prediction) {
            $speedGapModel = WindComparison::speedGapPercent($observedWindSpeed, $prediction->windSpeed);
            $directionGapModel = WindComparison::directionGapDegrees((float) $observedWindDirection, (float) $prediction->windDirection);
        }

        return new LiveWeatherComparison($speedGapForecast, $directionGapForecast, $speedGapModel, $directionGapModel);
    }
}

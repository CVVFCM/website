<?php

declare(strict_types=1);

namespace App\ML;

use App\Entity\WeatherForecastRecord;

/**
 * Builds the 9-feature input vector {@see WeatherModelInference} expects from a forecast record,
 * reproducing the feature engineering of the training SQL (wind vector + cyclical time encodings).
 */
final readonly class ForecastFeatureExtractor
{
    /**
     * @return list<float> in {@see WeatherModelInference::FEATURE_COLS} order
     */
    public static function fromRecord(WeatherForecastRecord $forecast): array
    {
        $directionRad = deg2rad((float) $forecast->windDirection);
        $dayOfYear = (float) ((int) $forecast->date->format('z') + 1); // z is 0-based; SQL EXTRACT(DOY) is 1-based
        $hour = (float) $forecast->date->format('G');

        $dayAngle = 2.0 * \M_PI * $dayOfYear / 365.25;
        $hourAngle = 2.0 * \M_PI * $hour / 24.0;

        return [
            $forecast->pressure,
            sin($directionRad) * $forecast->windSpeed,
            cos($directionRad) * $forecast->windSpeed,
            $forecast->humidity,
            $forecast->temperature,
            sin($dayAngle),
            cos($dayAngle),
            sin($hourAngle),
            cos($hourAngle),
        ];
    }
}

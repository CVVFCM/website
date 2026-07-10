<?php

declare(strict_types=1);

namespace App\Tests\ML;

use App\Entity\WeatherForecastRecord;
use App\ML\ForecastFeatureExtractor;
use App\ML\WeatherModelInference;
use PHPUnit\Framework\TestCase;

final class ForecastFeatureExtractorTest extends TestCase
{
    public function testItBuildsTheNineFeaturesInOrder(): void
    {
        $forecast = WeatherForecastRecord::fromArray([
            'date' => new \DateTimeImmutable('2026-01-01 12:00:00'),
            'humidity' => '90',
            'pressure' => '1013',
            'temperature' => '5',
            'windDirection' => '90',
            'windSpeed' => '10',
        ]);

        $features = ForecastFeatureExtractor::fromRecord($forecast);

        // Order must match the model's contract.
        $this->assertCount(9, $features);
        $this->assertSame(\count(WeatherModelInference::FEATURE_COLS), \count($features));

        $dayAngle = 2 * M_PI * 1 / 365.25;  // Jan 1 → day-of-year 1
        $hourAngle = 2 * M_PI * 12 / 24;    // noon

        $this->assertEqualsWithDelta(1013.0, $features[0], 1e-9);     // forecast_pressure
        $this->assertEqualsWithDelta(10.0, $features[1], 1e-9);       // forecast_wind_sin = sin(90°)*10
        $this->assertEqualsWithDelta(0.0, $features[2], 1e-9);        // forecast_wind_cos = cos(90°)*10
        $this->assertEqualsWithDelta(90.0, $features[3], 1e-9);       // forecast_humidity
        $this->assertEqualsWithDelta(5.0, $features[4], 1e-9);        // forecast_temperature
        $this->assertEqualsWithDelta(sin($dayAngle), $features[5], 1e-9);
        $this->assertEqualsWithDelta(cos($dayAngle), $features[6], 1e-9);
        $this->assertEqualsWithDelta(sin($hourAngle), $features[7], 1e-9);
        $this->assertEqualsWithDelta(cos($hourAngle), $features[8], 1e-9);
    }
}

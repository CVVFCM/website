<?php

declare(strict_types=1);

namespace App\Tests\Weather;

use App\DTO\LiveWeather;
use App\Entity\WeatherForecastRecord;
use App\ML\WeatherModelInference;
use App\Weather\LiveWeatherComparator;
use PHPUnit\Framework\TestCase;

final class LiveWeatherComparatorTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../ML/Fixtures';

    public function testItComputesForecastAndModelGaps(): void
    {
        $comparator = new LiveWeatherComparator($this->inference(self::FIXTURES.'/model_weights.json'));

        $comparison = $comparator->compare($this->observed(12.0, 100), $this->forecast(10.0, 80));

        // Forecast gaps: (12-10)/10*100 = +20 %, direction 100 vs 80 = +20°.
        $this->assertEqualsWithDelta(20.0, $comparison->windSpeedGapForecast, 1e-9);
        $this->assertEqualsWithDelta(20.0, $comparison->windDirectionGapForecast, 1e-9);

        // Model gaps computed against the ML correction (deterministic from the fixture).
        $this->assertNotNull($comparison->windSpeedGapModel);
        $this->assertNotNull($comparison->windDirectionGapModel);
        $this->assertTrue(is_finite($comparison->windSpeedGapModel));
        $this->assertGreaterThanOrEqual(-180.0, $comparison->windDirectionGapModel);
        $this->assertLessThanOrEqual(180.0, $comparison->windDirectionGapModel);
    }

    public function testEverythingIsNullWithoutAForecast(): void
    {
        $comparator = new LiveWeatherComparator($this->inference(self::FIXTURES.'/model_weights.json'));

        $comparison = $comparator->compare($this->observed(12.0, 100), null);

        $this->assertNull($comparison->windSpeedGapForecast);
        $this->assertNull($comparison->windDirectionGapForecast);
        $this->assertNull($comparison->windSpeedGapModel);
        $this->assertNull($comparison->windDirectionGapModel);
    }

    public function testModelGapsAreNullWhenTheModelIsUnavailable(): void
    {
        $comparator = new LiveWeatherComparator($this->inference(self::FIXTURES.'/does-not-exist.json'));

        $comparison = $comparator->compare($this->observed(12.0, 100), $this->forecast(10.0, 80));

        // Forecast gaps still computed; model gaps degrade to null.
        $this->assertEqualsWithDelta(20.0, $comparison->windSpeedGapForecast, 1e-9);
        $this->assertNull($comparison->windSpeedGapModel);
        $this->assertNull($comparison->windDirectionGapModel);
    }

    private function inference(string $weightsPath): WeatherModelInference
    {
        return new WeatherModelInference($weightsPath, self::FIXTURES.'/scaler_params.json');
    }

    private function observed(float $windSpeed, int $windDirection): LiveWeather
    {
        $weather = new LiveWeather();
        $weather->windSpeed = $windSpeed;
        $weather->windDirection = $windDirection;

        return $weather;
    }

    private function forecast(float $windSpeed, int $windDirection): WeatherForecastRecord
    {
        return WeatherForecastRecord::fromArray([
            'date' => new \DateTimeImmutable('2026-01-01 12:00:00'),
            'humidity' => '90',
            'pressure' => '1013',
            'temperature' => '5',
            'windDirection' => (string) $windDirection,
            'windSpeed' => (string) $windSpeed,
        ]);
    }
}

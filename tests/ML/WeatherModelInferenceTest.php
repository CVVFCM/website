<?php

declare(strict_types=1);

namespace App\Tests\ML;

use App\ML\WeatherModelInference;
use App\ML\WeatherPrediction;
use PHPUnit\Framework\TestCase;

final class WeatherModelInferenceTest extends TestCase
{
    private function inference(): WeatherModelInference
    {
        return new WeatherModelInference(
            __DIR__.'/Fixtures/model_weights.json',
            __DIR__.'/Fixtures/scaler_params.json',
        );
    }

    public function testPredictReturnsAWellFormedPrediction(): void
    {
        $features = [1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0];

        $prediction = $this->inference()->predict($features);

        $this->assertInstanceOf(WeatherPrediction::class, $prediction);
        $this->assertTrue(is_finite($prediction->temperature));
        $this->assertTrue(is_finite($prediction->windSpeed));
        $this->assertGreaterThanOrEqual(0.0, $prediction->windSpeed);
        $this->assertGreaterThanOrEqual(0, $prediction->windDirection);
        $this->assertLessThan(360, $prediction->windDirection);
    }

    public function testPredictIsDeterministic(): void
    {
        $features = [1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0];
        $inference = $this->inference();

        $first = $inference->predict($features);
        $second = $inference->predict($features);

        $this->assertSame($first->temperature, $second->temperature);
        $this->assertSame($first->windSpeed, $second->windSpeed);
        $this->assertSame($first->windDirection, $second->windDirection);
    }

    public function testItThrowsWhenAnArtifactIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        new WeatherModelInference(__DIR__.'/Fixtures/does-not-exist.json', __DIR__.'/Fixtures/scaler_params.json');
    }
}

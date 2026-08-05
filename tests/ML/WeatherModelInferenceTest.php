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

        $this->assertSame($first->windSpeed, $second->windSpeed);
        $this->assertSame($first->windDirection, $second->windDirection);
    }

    public function testConstructionDoesNotTouchTheArtifacts(): void
    {
        // Lazy loading: a missing artifact must not break construction (pods boot without the model).
        $inference = new WeatherModelInference(__DIR__.'/Fixtures/does-not-exist.json', __DIR__.'/Fixtures/scaler_params.json');

        $this->assertInstanceOf(WeatherModelInference::class, $inference);
    }

    public function testPredictThrowsWhenAnArtifactIsMissing(): void
    {
        $inference = new WeatherModelInference(__DIR__.'/Fixtures/does-not-exist.json', __DIR__.'/Fixtures/scaler_params.json');

        $this->expectException(\RuntimeException::class);
        $inference->predict([1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0]);
    }

    public function testTryPredictReturnsNullWhenTheModelIsUnavailable(): void
    {
        $inference = new WeatherModelInference(__DIR__.'/Fixtures/does-not-exist.json', __DIR__.'/Fixtures/scaler_params.json');

        $this->assertNull($inference->tryPredict([1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0]));
    }

    public function testTryPredictLogsAWarningWhenAnArtifactIsMissing(): void
    {
        $logger = new SpyLogger();
        $weightsPath = __DIR__.'/Fixtures/does-not-exist.json';
        $inference = new WeatherModelInference($weightsPath, __DIR__.'/Fixtures/scaler_params.json', $logger);

        $this->assertNull($inference->tryPredict([1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0]));

        $this->assertCount(1, $logger->records);
        $record = $logger->records[0];
        $this->assertSame('warning', $record['level']);
        $this->assertSame($weightsPath, $record['context']['weightsPath']);
        $this->assertSame(__DIR__.'/Fixtures/scaler_params.json', $record['context']['scalerPath']);
        $this->assertIsString($record['context']['error']);
        $this->assertStringContainsString('does-not-exist.json', $record['context']['error']);
    }

    public function testTryPredictReturnsNullAndLogsOnCorruptJson(): void
    {
        // A half-written artifact (e.g. interrupted delivery) must degrade, not abort the cron run.
        $logger = new SpyLogger();
        $inference = new WeatherModelInference(__DIR__.'/Fixtures/corrupt.json', __DIR__.'/Fixtures/scaler_params.json', $logger);

        $this->assertNull($inference->tryPredict([1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0]));
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
    }

    public function testTryPredictReturnsNullAndLogsOnMalformedShapes(): void
    {
        // Valid JSON, wrong structure: the TypeError must be contained, not escape tryPredict().
        $logger = new SpyLogger();
        $inference = new WeatherModelInference(__DIR__.'/Fixtures/malformed_weights.json', __DIR__.'/Fixtures/scaler_params.json', $logger);

        $this->assertNull($inference->tryPredict([1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0]));
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
    }

    public function testTryPredictDoesNotLogOnSuccess(): void
    {
        $logger = new SpyLogger();
        $inference = new WeatherModelInference(
            __DIR__.'/Fixtures/model_weights.json',
            __DIR__.'/Fixtures/scaler_params.json',
            $logger,
        );

        $this->assertInstanceOf(WeatherPrediction::class, $inference->tryPredict([1013.0, 0.5, 1.5, 90.0, 2.0, -0.1, 0.99, 0.0, 1.0]));
        $this->assertSame([], $logger->records);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\ML;

use App\ML\WeatherModelInference;
use PHPUnit\Framework\TestCase;

/**
 * Guards the pure-PHP inference against the Python/ONNX reference. The fixture bundle
 * (tests/ML/Fixtures) is produced by ml/src/view_model_accuracy.py: raw feature vectors + the
 * physical outputs ONNX yields for them. PHP runs in float64 vs ONNX float32, so a small tolerance
 * is expected; a real bug (wrong layout, missing scaler, bad wind maths) blows well past it.
 */
final class InferenceParityTest extends TestCase
{
    private const float VALUE_TOLERANCE = 1e-2;   // °C / knots
    private const float DIRECTION_TOLERANCE = 2.0; // degrees, only meaningful above light wind

    public function testPhpMatchesPythonOnEveryCase(): void
    {
        $inference = new WeatherModelInference(
            __DIR__.'/Fixtures/model_weights.json',
            __DIR__.'/Fixtures/scaler_params.json',
        );

        $cases = $this->cases();
        $this->assertNotEmpty($cases);

        foreach ($cases as $index => $case) {
            $prediction = $inference->predict($case['features']);
            $expected = $case['expected'];

            $this->assertEqualsWithDelta(
                $expected['temperature'],
                $prediction->temperature,
                self::VALUE_TOLERANCE,
                sprintf('temperature mismatch on case #%d', $index),
            );
            $this->assertEqualsWithDelta(
                $expected['windSpeed'],
                $prediction->windSpeed,
                self::VALUE_TOLERANCE,
                sprintf('windSpeed mismatch on case #%d', $index),
            );

            // Bearing is unstable when there is almost no wind; only assert it once there is some.
            if ($expected['windSpeed'] > 1.0) {
                $delta = abs($expected['windDirection'] - $prediction->windDirection) % 360;
                $delta = min($delta, 360 - $delta);
                $this->assertLessThanOrEqual(
                    self::DIRECTION_TOLERANCE,
                    $delta,
                    sprintf('windDirection mismatch on case #%d (%d vs %d)', $index, $expected['windDirection'], $prediction->windDirection),
                );
            }
        }
    }

    /**
     * @return list<array{features: list<float>, expected: array{temperature: float, windSpeed: float, windDirection: int}}>
     */
    private function cases(): array
    {
        $raw = (string) file_get_contents(__DIR__.'/Fixtures/inference_cases.json');

        /** @var array{cases: list<array{features: list<float>, expected: array{temperature: float, windSpeed: float, windDirection: int}}>} $data */
        $data = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

        return $data['cases'];
    }
}

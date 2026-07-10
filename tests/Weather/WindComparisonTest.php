<?php

declare(strict_types=1);

namespace App\Tests\Weather;

use App\Weather\WindComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WindComparisonTest extends TestCase
{
    public function testSpeedGapIsSignedPercent(): void
    {
        $this->assertEqualsWithDelta(25.0, WindComparison::speedGapPercent(10.0, 8.0), 1e-9);
        $this->assertEqualsWithDelta(-50.0, WindComparison::speedGapPercent(5.0, 10.0), 1e-9);
    }

    public function testSpeedGapIsNullWhenReferenceIsZero(): void
    {
        $this->assertNull(WindComparison::speedGapPercent(4.0, 0.0));
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function directionCases(): iterable
    {
        yield 'identical' => [90.0, 90.0, 0.0];
        yield 'clockwise across wrap' => [10.0, 350.0, 20.0];   // observed 20° CW of reference
        yield 'counter-clockwise across wrap' => [350.0, 10.0, -20.0];
        yield 'small positive' => [100.0, 80.0, 20.0];
        yield 'opposite' => [180.0, 0.0, -180.0];               // (-180, 180] → -180
    }

    #[DataProvider('directionCases')]
    public function testDirectionGapIsSignedShortestTurn(float $observed, float $reference, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, WindComparison::directionGapDegrees($observed, $reference), 1e-9);
    }
}

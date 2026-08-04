<?php

declare(strict_types=1);

namespace App\Tests\Weather;

use App\Weather\CardinalDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CardinalDirectionTest extends TestCase
{
    /**
     * @return iterable<string, array{int, CardinalDirection}>
     */
    public static function directionCases(): iterable
    {
        yield 'north' => [0, CardinalDirection::N];
        yield 'north from the west side of the wrap' => [350, CardinalDirection::N];
        yield 'north upper bound' => [22, CardinalDirection::N];
        yield 'north-east lower bound' => [23, CardinalDirection::NE];
        yield 'north-east' => [45, CardinalDirection::NE];
        yield 'east' => [90, CardinalDirection::E];
        yield 'south-east' => [135, CardinalDirection::SE];
        yield 'south' => [180, CardinalDirection::S];
        yield 'south-west' => [225, CardinalDirection::SW];
        yield 'west' => [270, CardinalDirection::W];
        yield 'north-west upper bound' => [337, CardinalDirection::NW];
        yield 'north lower bound' => [338, CardinalDirection::N];
        yield 'full circle' => [360, CardinalDirection::N];
        yield 'negative wraps into the north window' => [-10, CardinalDirection::N];
        yield 'negative wraps into north-west' => [-30, CardinalDirection::NW];
    }

    #[DataProvider('directionCases')]
    public function testFromDirection(int $direction, CardinalDirection $expected): void
    {
        $this->assertSame($expected, CardinalDirection::fromDirection($direction));
    }
}

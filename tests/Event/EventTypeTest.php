<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\EventType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function labelCases(): iterable
    {
        yield 'default type' => ['default', 'Événement'];
        yield 'regatta' => ['regatta', 'Régate'];
        yield 'sea trip' => ['sea_trip', 'Sortie en mer'];
        yield 'stage' => ['stage', 'Stage'];
        yield 'efv' => ['efv', 'EFV'];
        yield 'missing type falls back to the generic label' => [null, 'Événement'];
        yield 'unknown type falls back to the generic label' => ['foo', 'Événement'];
    }

    #[DataProvider('labelCases')]
    public function testItLabelsARawTypeValue(?string $type, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, EventType::labelFor($type));
    }
}

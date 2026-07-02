<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\CurrentDateTimeTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

final class CurrentDateTimeToolTest extends TestCase
{
    protected function tearDown(): void
    {
        // NativeClock, not `new Clock()`: an inner-less Clock delegates to the global
        // clock — itself here — and recurses infinitely.
        Clock::set(new NativeClock());
    }

    public function testItGivesParisDateTime(): void
    {
        // 2026-07-02 12:00 UTC = 14:00 in Paris (CEST), a Thursday.
        Clock::set(new MockClock(new \DateTimeImmutable('2026-07-02 12:00:00', new \DateTimeZone('UTC'))));

        $this->assertSame(
            [
                'datetime' => '2026-07-02 14:00:00',
                'jour' => 'Thursday',
                'fuseau' => 'Europe/Paris',
            ],
            new CurrentDateTimeTool()(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\EventTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EventToolTest extends KernelTestCase
{
    public function testUpcomingCalendarMixesEventTypes(): void
    {
        $result = $this->tool()('a_venir');

        $this->assertSame('à venir (12 prochains mois)', $result['periode']);
        $this->assertNotEmpty($result['evenements']);

        $now = new \DateTimeImmutable();
        $max = $now->modify('+1 year');
        $types = [];
        $previous = null;
        foreach ($result['evenements'] as $event) {
            $this->assertSame(
                ['titre', 'type', 'debut', 'fin', 'lieu', 'url', 'description'],
                array_keys($event),
            );
            $this->assertContains($event['type'], ['Événement', 'Régate', 'Sortie en mer']);
            $types[] = $event['type'];

            $begin = new \DateTimeImmutable($event['debut']);
            $this->assertGreaterThanOrEqual($now->modify('-1 day'), $begin);
            $this->assertLessThan($max, $begin);
            if (null !== $previous) {
                $this->assertGreaterThanOrEqual($previous, $begin);
            }
            $previous = $begin;
        }

        // Fixtures guarantee at least regattas in the coming year.
        $this->assertContains('Régate', $types);
    }

    public function testPastPeriodIsDescendingWithinAYear(): void
    {
        $result = $this->tool()('passees');

        $now = new \DateTimeImmutable();
        $min = $now->modify('-1 year');
        $previous = null;
        foreach ($result['evenements'] as $event) {
            $begin = new \DateTimeImmutable($event['debut']);
            $this->assertLessThan($now, $begin);
            $this->assertGreaterThanOrEqual($min, $begin);
            if (null !== $previous) {
                $this->assertLessThanOrEqual($previous, $begin);
            }
            $previous = $begin;
        }
    }

    private function tool(): EventTool
    {
        self::bootKernel();

        return static::getContainer()->get(EventTool::class);
    }
}

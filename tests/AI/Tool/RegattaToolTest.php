<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\RegattaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegattaToolTest extends KernelTestCase
{
    private const array REGATTA_NAMES = [
        'Trophée du Coeur de l\'Europe',
        'Coupe Bernard Bozier',
        'Tour des Lacs Yole OK',
        'Femmes à la Barre - L\'Ardennaise',
        'National Maraudeur',
    ];

    public function testUpcomingReturnsOnlyFutureRegattasWithinAYearAscending(): void
    {
        $result = $this->tool()->upcoming();

        $this->assertNotEmpty($result['regates']);

        $now = new \DateTimeImmutable();
        $max = $now->modify('+1 year');
        $previous = null;
        foreach ($result['regates'] as $regatta) {
            $begin = new \DateTimeImmutable($regatta['debut']);
            $this->assertGreaterThanOrEqual($now->modify('-1 day'), $begin);
            $this->assertLessThan($max, $begin);

            if (null !== $previous) {
                $this->assertGreaterThanOrEqual($previous, $begin, 'Upcoming regattas must be sorted soonest first');
            }
            $previous = $begin;

            $this->assertRegattaShape($regatta);
        }
    }

    public function testPastReturnsOnlyPastRegattasWithinAYearDescending(): void
    {
        $result = $this->tool()->past();

        $this->assertNotEmpty($result['regates']);

        $now = new \DateTimeImmutable();
        $min = $now->modify('-1 year');
        $previous = null;
        foreach ($result['regates'] as $regatta) {
            $begin = new \DateTimeImmutable($regatta['debut']);
            $this->assertLessThan($now, $begin);
            $this->assertGreaterThanOrEqual($min, $begin);

            if (null !== $previous) {
                $this->assertLessThanOrEqual($previous, $begin, 'Past regattas must be sorted most recent first');
            }
            $previous = $begin;

            $this->assertRegattaShape($regatta);
        }
    }

    /**
     * @param array<string, mixed> $regatta
     */
    private function assertRegattaShape(array $regatta): void
    {
        $this->assertSame(
            ['titre', 'debut', 'fin', 'url', 'lieu', 'description', 'series', 'informations'],
            array_keys($regatta),
        );

        // Only regattas (event_type=regatta) may surface: fixture regatta editions are "<name> <year>".
        $isKnownRegatta = array_any(
            self::REGATTA_NAMES,
            static fn (string $name): bool => str_starts_with((string) $regatta['titre'], $name),
        );
        $this->assertTrue($isKnownRegatta, sprintf('Unexpected non-regatta result "%s"', $regatta['titre']));

        $this->assertNotEmpty($regatta['series'], 'Rich payload must expose the series/grades');
        $this->assertNotNull($regatta['informations']);
    }

    private function tool(): RegattaTool
    {
        self::bootKernel();

        return static::getContainer()->get(RegattaTool::class);
    }
}

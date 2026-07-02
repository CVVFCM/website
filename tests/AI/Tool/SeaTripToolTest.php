<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\SeaTripTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SeaTripToolTest extends KernelTestCase
{
    public function testUpcomingSeaTripsExposeAllData(): void
    {
        $result = $this->tool()('a_venir');

        $this->assertSame('à venir (24 prochains mois)', $result['periode']);
        $this->assertNotEmpty($result['sorties']);

        $now = new \DateTimeImmutable();
        $previous = null;
        foreach ($result['sorties'] as $trip) {
            $this->assertSame(
                ['titre', 'debut', 'fin', 'url', 'lieu', 'description', 'bateaux', 'liens', 'contacts'],
                array_keys($trip),
            );

            $begin = new \DateTimeImmutable($trip['debut']);
            $this->assertGreaterThanOrEqual($now->modify('-1 day'), $begin);
            if (null !== $previous) {
                $this->assertGreaterThanOrEqual($previous, $begin, 'Upcoming trips must be sorted soonest first');
            }
            $previous = $begin;

            $this->assertNotEmpty($trip['bateaux'], 'Sea trips must expose their boats');
            foreach ($trip['bateaux'] as $boat) {
                $this->assertSame(
                    ['type_bateau', 'chef_de_bord', 'places', 'prix_approximatif_par_personne'],
                    array_keys($boat),
                );
                $this->assertNotEmpty($boat['chef_de_bord'], 'Each boat must have a resolved captain');
            }
        }
    }

    public function testPastPeriodExcludesFutureTrips(): void
    {
        $result = $this->tool()('passees');

        $this->assertSame('passées (24 derniers mois)', $result['periode']);

        $now = new \DateTimeImmutable();
        foreach ($result['sorties'] as $trip) {
            $this->assertLessThan($now, new \DateTimeImmutable($trip['debut']));
        }
    }

    private function tool(): SeaTripTool
    {
        self::bootKernel();

        return static::getContainer()->get(SeaTripTool::class);
    }
}

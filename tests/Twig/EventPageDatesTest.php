<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class EventPageDatesTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function midnightCases(): iterable
    {
        yield 'single day without end date' => ['2027-06-06T00:00:00', null];
        yield 'begin and end on the same day, both at midnight' => ['2027-06-06T00:00:00', '2027-06-06T00:00:00'];
    }

    #[DataProvider('midnightCases')]
    public function testItHidesTheTimeLineWhenTimesAreLeftAtMidnight(string $beginDate, ?string $endDate): void
    {
        $html = $this->render($beginDate, $endDate);

        $this->assertStringContainsString('DIMANCHE 6 JUIN 2027', $html);
        $this->assertStringNotContainsString('00H00', $html);
        $this->assertStringNotContainsString('EventPage__headerTime', $html);
    }

    public function testItShowsATimeRangeWhenBothTimesAreSet(): void
    {
        $html = $this->render('2027-06-06T14:30:00', '2027-06-06T16:40:00');

        $this->assertStringContainsString('DIMANCHE 6 JUIN 2027', $html);
        $this->assertStringContainsString('DE 14H30 À 16H40', $html);
    }

    public function testItShowsOnlyTheBeginTimeWhenTheEndTimeIsLeftAtMidnight(): void
    {
        $html = $this->render('2027-06-06T14:30:00', '2027-06-06T00:00:00');

        $this->assertStringContainsString('DIMANCHE 6 JUIN 2027', $html);
        $this->assertStringContainsString('À 14H30', $html);
        $this->assertStringNotContainsString('DE 14H30', $html);
        $this->assertStringNotContainsString('00H00', $html);
    }

    public function testItShowsBothDaysForAMultiDayEvent(): void
    {
        $html = $this->render('2027-06-05T00:00:00', '2027-06-06T00:00:00');

        // Asserted in two halves: the template breaks the line between them, and pinning the
        // markup in between made this fail the moment a <br /> was introduced.
        $this->assertStringContainsString('DU SAMEDI 5 JUIN 2027', $html);
        $this->assertStringContainsString('AU DIMANCHE 6 JUIN 2027', $html);
        $this->assertStringNotContainsString('00H00', $html);
        $this->assertStringNotContainsString('EventPage__headerTime', $html);
    }

    private function render(string $beginDate, ?string $endDate): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('partials/event_page_dates.html.twig', [
            'begin_date' => $beginDate,
            'end_date' => $endDate,
        ]);
    }
}

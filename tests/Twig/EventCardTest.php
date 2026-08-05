<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class EventCardTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function defaultImageCases(): iterable
    {
        yield 'regatta gets its own illustration' => ['regatta', 'images/events/regatta'];
        yield 'stage gets its own illustration' => ['stage', 'images/events/stage'];
        yield 'sea trip falls back to the generic one' => ['sea_trip', 'images/events/default'];
        yield 'default type' => ['default', 'images/events/default'];
        yield 'missing type' => [null, 'images/events/default'];
    }

    #[DataProvider('defaultImageCases')]
    public function testItRendersATypeSpecificDefaultImageWhenTheEventHasNoMedia(?string $eventType, string $expectedImage): void
    {
        $html = $this->render($eventType);

        $this->assertStringContainsString('EventCard__image', $html);
        $this->assertStringContainsString($expectedImage, $html);
        $this->assertStringContainsString('alt=""', $html);
    }

    public function testItLabelsTheStageType(): void
    {
        $this->assertStringContainsString('Stage', $this->render('stage'));
    }

    public function testItShowsASingleDateForASingleDayEvent(): void
    {
        $html = $this->renderWithDates('2027-04-17T00:00:00', '2027-04-17T18:00:00');

        $this->assertSame(1, substr_count($html, '17 AVRIL 2027'));
        $this->assertStringNotContainsString('–', $html);
    }

    public function testItShowsTheBeginDateWhenTheEndDateIsMissing(): void
    {
        $html = $this->renderWithDates('2027-04-24T00:00:00', null);

        $this->assertStringContainsString('24 AVRIL 2027', $html);
        $this->assertStringNotContainsString('–', $html);
        $this->assertStringNotContainsString((new \DateTimeImmutable())->format('Y'), $html);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function dateRangeCases(): iterable
    {
        yield 'multi-day within the same month' => ['2027-04-17T00:00:00', '2027-04-18T00:00:00', '17–18 AVRIL 2027'];
        yield 'range crossing a month boundary' => ['2027-04-30T00:00:00', '2027-05-02T00:00:00', '30 AVRIL – 2 MAI 2027'];
    }

    #[DataProvider('dateRangeCases')]
    public function testItFormatsMultiDayDateRanges(string $beginDate, string $endDate, string $expected): void
    {
        $this->assertStringContainsString($expected, $this->renderWithDates($beginDate, $endDate));
    }

    private function renderWithDates(string $beginDate, ?string $endDate): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('partials/event_card.html.twig', ['event' => [
            'title' => 'Événement de test',
            'url' => '/evenements/test',
            'begin_date' => $beginDate,
            'end_date' => $endDate,
            'main_media' => null,
        ]]);
    }

    private function render(?string $eventType): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $event = [
            'title' => 'Événement de test',
            'url' => '/evenements/test',
            'begin_date' => '2027-06-12T10:00:00',
            'end_date' => '2027-06-13T18:00:00',
            'main_media' => null,
        ];
        if (null !== $eventType) {
            $event['event_type'] = $eventType;
        }

        return $twig->render('partials/event_card.html.twig', ['event' => $event]);
    }
}

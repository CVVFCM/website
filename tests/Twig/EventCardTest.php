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

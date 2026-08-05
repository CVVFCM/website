<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The list page extends pages/default.html.twig and needs a full Sulu website
 * context to render as a whole, so only its MainContent block is rendered in
 * isolation — the same way Sulu's preview renders blocks.
 */
final class ListPageTest extends KernelTestCase
{
    public function testAnEventListOffersTheCalendarFeedOnce(): void
    {
        $html = $this->renderMainContent('event');

        $this->assertStringContainsString('ListPage__actions', $html);
        $this->assertSame(1, substr_count($html, 'ListPage__calendarLink'));
        $this->assertStringContainsString('/evenements.ics', $html);
        $this->assertStringContainsString('download', $html);
        $this->assertStringContainsString('Ajouter les événements à mon calendrier', $html);
    }

    /**
     * Issue #90: the feed button is the only add-to-calendar action, the cards
     * below it carry none.
     */
    public function testTheCardsThemselvesCarryNoCalendarAction(): void
    {
        $html = $this->renderMainContent('event');

        $this->assertStringContainsString('EventCard', $html);
        $this->assertStringNotContainsString('EventCard__calendar', $html);
    }

    public function testAPageListHasNoCalendarFeed(): void
    {
        $html = $this->renderMainContent('page');

        $this->assertStringNotContainsString('ListPage__actions', $html);
        $this->assertStringNotContainsString('ListPage__calendarLink', $html);
        $this->assertStringNotContainsString('/evenements.ics', $html);
    }

    private function renderMainContent(string $listType): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $event = [
            'title' => 'Événement de test',
            'url' => '/evenements/test',
            'begin_date' => '2027-06-12T10:00:00',
            'end_date' => '2027-06-13T18:00:00',
            'event_type' => 'regatta',
            'main_media' => null,
        ];
        $page = [
            'title' => 'Page de test',
            'url' => '/page-de-test',
            'description' => '<p>Une page.</p>',
            'main_media' => null,
        ];

        return $twig->load('pages/list.html.twig')->renderBlock('MainContent', [
            'content' => [
                'list_type' => $listType,
                'event_list' => [$event],
                'page_list' => [$page],
            ],
            'view' => [
                'event_list' => null,
                'page_list' => null,
            ],
        ]);
    }
}

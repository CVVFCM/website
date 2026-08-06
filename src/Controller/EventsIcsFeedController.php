<?php

declare(strict_types=1);

namespace App\Controller;

use App\AI\PageContentRepository;
use App\AI\TemplateData;
use App\Calendar\CalendarEvent;
use App\Calendar\IcsBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves every upcoming published event as one downloadable iCalendar file, so
 * a visitor adds the whole club season to their calendar with a single click
 * from the events list page (issue #90 replaced the per-card buttons by this).
 *
 * An attribute route rather than a Sulu content page: the file is derived from
 * the published pages, there is nothing for an editor to author, and Sulu's
 * content route would force an editorial page to exist just to expose it.
 * Serving text/calendar also keeps Sulu's AppendAnalyticsListener away, since
 * it only rewrites text/html responses — an analytics snippet appended to the
 * ICS body would corrupt the calendar.
 *
 * Each VEVENT keeps the page uuid as its UID, so downloading the feed again
 * after an edit updates the existing entries instead of duplicating them.
 */
#[Route('/evenements.ics', name: 'events_ics_feed', methods: ['GET'])]
final readonly class EventsIcsFeedController
{
    private const int DESCRIPTION_MAX_LENGTH = 300;
    private const string CALENDAR_NAME = 'Événements CVVFCM';

    public function __construct(
        private PageContentRepository $pageContentRepository,
        private IcsBuilder $icsBuilder,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $host = $request->getSchemeAndHttpHost();

        /** @var list<CalendarEvent> $events */
        $events = [];
        foreach ($this->pageContentRepository->findUpcomingEvents($this->clock->now()) as $row) {
            $uuid = $this->string($row['uuid'] ?? null);
            $title = $this->string($row['title'] ?? null);
            $beginDate = $this->string($row['begin_date'] ?? null);
            if (null === $uuid || null === $title || null === $beginDate) {
                continue;
            }

            $events[] = new CalendarEvent(
                uuid: $uuid,
                title: $title,
                beginDate: $beginDate,
                endDate: $this->string($row['end_date'] ?? null),
                url: $host.'/'.ltrim($this->string($row['url'] ?? null) ?? '/', '/'),
                location: TemplateData::location($row),
                description: $this->truncate(TemplateData::plainText($row['description'] ?? null)),
            );
        }

        // An empty season is a valid, empty calendar — never a 404.
        $ics = $this->icsBuilder->buildFeed($events, self::CALENDAR_NAME);

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="evenements-cvvfcm.ics"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function truncate(?string $text): ?string
    {
        if (null === $text || mb_strlen($text) <= self::DESCRIPTION_MAX_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::DESCRIPTION_MAX_LENGTH);
        $lastSpace = mb_strrpos($cut, ' ');
        if (false !== $lastSpace && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut).'…';
    }
}

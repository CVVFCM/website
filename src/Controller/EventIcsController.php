<?php

declare(strict_types=1);

namespace App\Controller;

use App\AI\PageContentRepository;
use App\AI\TemplateData;
use App\Calendar\IcsBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves a published event as a downloadable iCalendar file, so visitors can
 * add it to their calendar from the event page pill and the event list cards.
 * Keyed on the page uuid: the ICS UID stays stable across edits, so a
 * re-download after a date change updates the calendar entry instead of
 * duplicating it. The text/calendar content type also keeps Sulu's
 * AppendAnalyticsListener away (it only rewrites text/html responses).
 */
#[Route(
    '/evenements/{uuid}.ics',
    name: 'event_ics',
    requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
    methods: ['GET'],
)]
final readonly class EventIcsController
{
    private const int DESCRIPTION_MAX_LENGTH = 300;

    public function __construct(
        private PageContentRepository $pageContentRepository,
        private IcsBuilder $icsBuilder,
    ) {
    }

    public function __invoke(Request $request, string $uuid): Response
    {
        $event = $this->pageContentRepository->findEventByUuid($uuid);
        if (null === $event) {
            throw new NotFoundHttpException(\sprintf('No published event with uuid "%s".', $uuid));
        }

        $title = $this->string($event['title'] ?? null);
        $beginDate = $this->string($event['begin_date'] ?? null);
        if (null === $title || null === $beginDate) {
            throw new NotFoundHttpException(\sprintf('Event "%s" has no title or begin date.', $uuid));
        }

        $url = $this->string($event['url'] ?? null) ?? '/';

        $ics = $this->icsBuilder->build(
            uuid: $uuid,
            title: $title,
            beginDate: $beginDate,
            endDate: $this->string($event['end_date'] ?? null),
            url: $request->getSchemeAndHttpHost().'/'.ltrim($url, '/'),
            location: TemplateData::location($event),
            description: $this->truncate(TemplateData::plainText($event['description'] ?? null)),
        );

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => \sprintf('attachment; filename="%s"', $this->filename($url)),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * ASCII-safe filename from the last slug segment (slugs are already
     * ASCII; anything else is stripped defensively).
     */
    private function filename(string $url): string
    {
        $segment = preg_replace('/[^A-Za-z0-9._-]/', '', basename($url)) ?? '';

        return ('' === $segment ? 'evenement' : $segment).'.ics';
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

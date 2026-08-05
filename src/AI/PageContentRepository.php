<?php

declare(strict_types=1);

namespace App\AI;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContent;

/**
 * Read access to published Sulu page content (fr, live stage) for the AI tools
 * and the ICS export. Returns raw templateData arrays; mapping to tool payloads
 * happens in the callers.
 */
final readonly class PageContentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Published "event" pages within a begin_date window, ordered by begin_date.
     *
     * @param string|null $eventType filter on templateData.event_type, null = all event types
     *
     * @return list<array<string, mixed>>
     */
    public function findEvents(?string $eventType, \DateTimeImmutable $from, \DateTimeImmutable $to, string $order): array
    {
        $queryBuilder = $this->createPublishedQueryBuilder()
            ->andWhere('dc.templateKey = :templateKey')
            ->setParameter('templateKey', 'event')
            ->addSelect("JSON_GET_TEXT(dc.templateData, 'begin_date') AS HIDDEN begin_date")
            ->andWhere("JSON_GET_TEXT(dc.templateData, 'begin_date') >= :from")
            ->andWhere("JSON_GET_TEXT(dc.templateData, 'begin_date') < :to")
            ->orderBy('begin_date', 'DESC' === $order ? 'DESC' : 'ASC')
            ->setParameter('from', $from->format('Y-m-d\TH:i:s'))
            ->setParameter('to', $to->format('Y-m-d\TH:i:s'));

        if (null !== $eventType) {
            $queryBuilder
                ->andWhere("JSON_GET_TEXT(dc.templateData, 'event_type') = :eventType")
                ->setParameter('eventType', $eventType);
        }

        return $this->templateData($queryBuilder);
    }

    /**
     * Published "event" pages starting today or later, oldest first.
     *
     * Rows carry the page uuid on top of the templateData, so the ICS feed can
     * derive one stable UID per event.
     *
     * @return list<array<string, mixed>>
     */
    public function findUpcomingEvents(\DateTimeImmutable $from): array
    {
        return $this->templateData(
            $this->createPublishedQueryBuilder()
                ->innerJoin('dc.page', 'p')
                ->addSelect('p.uuid')
                ->andWhere('dc.templateKey = :templateKey')
                ->setParameter('templateKey', 'event')
                ->addSelect("JSON_GET_TEXT(dc.templateData, 'begin_date') AS HIDDEN begin_date")
                // begin_date is stored as an ISO-8601 string, so a plain string
                // comparison against "Y-m-d" keeps the whole starting day in.
                ->andWhere("JSON_GET_TEXT(dc.templateData, 'begin_date') >= :from")
                ->setParameter('from', $from->format('Y-m-d'))
                ->orderBy('begin_date', 'ASC'),
        );
    }

    /**
     * All published pages excluding the given template keys (e.g. "event").
     *
     * @param list<string> $excludedTemplateKeys
     *
     * @return list<array<string, mixed>>
     */
    public function findPages(array $excludedTemplateKeys = []): array
    {
        $queryBuilder = $this->createPublishedQueryBuilder();

        if ([] !== $excludedTemplateKeys) {
            $queryBuilder
                ->andWhere('dc.templateKey NOT IN (:excludedTemplateKeys)')
                ->setParameter('excludedTemplateKeys', $excludedTemplateKeys);
        }

        return $this->templateData($queryBuilder);
    }

    /**
     * A single published page by its route slug.
     *
     * @return array<string, mixed>|null
     */
    public function findPageByUrl(string $url): ?array
    {
        $rows = $this->templateData(
            $this->createPublishedQueryBuilder()
                ->andWhere('route.slug = :url')
                ->setParameter('url', $url)
                ->setMaxResults(1),
        );

        return $rows[0] ?? null;
    }

    /**
     * A single published "event" page by its page uuid.
     *
     * @return array<string, mixed>|null
     */
    public function findEventByUuid(string $uuid): ?array
    {
        $rows = $this->templateData(
            $this->createPublishedQueryBuilder()
                ->innerJoin('dc.page', 'p')
                ->andWhere('p.uuid = :uuid')
                ->andWhere('dc.templateKey = :templateKey')
                ->setParameter('uuid', $uuid)
                ->setParameter('templateKey', 'event')
                ->setMaxResults(1),
        );

        return $rows[0] ?? null;
    }

    private function createPublishedQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('dc.templateData')
            // The canonical url is the route slug; templateData.url is only reliably
            // set on event pages.
            ->addSelect('route.slug AS url_slug')
            ->from(PageDimensionContent::class, 'dc')
            ->leftJoin('dc.route', 'route')
            ->where('dc.locale = :locale')
            ->andWhere('dc.stage = :stage')
            ->setParameter('locale', 'fr')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateData(\Doctrine\ORM\QueryBuilder $queryBuilder): array
    {
        // "uuid" is only selected by the queries that join dc.page, hence optional.
        /** @var list<array{templateData: array<string, mixed>, url_slug: string|null, uuid?: string}> $rows */
        $rows = $queryBuilder->getQuery()->getArrayResult();

        return array_map(
            static function (array $row): array {
                $data = $row['templateData'];
                if (null !== $row['url_slug']) {
                    $data['url'] = $row['url_slug'];
                }
                if (isset($row['uuid'])) {
                    $data['uuid'] = $row['uuid'];
                }

                return $data;
            },
            $rows,
        );
    }
}

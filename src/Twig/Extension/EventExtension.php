<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function Symfony\Component\Clock\now;

final class EventExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaManagerInterface $mediaManager,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('next_featured_event_url', $this->getNextFeaturedEventUrl(...)),
            new TwigFunction('next_list_events', $this->getNextListEvents(...)),
        ];
    }

    public function getNextFeaturedEventUrl(): ?string
    {
        /** @var array{url: string}|null $result */
        $result = $this->entityManager
            ->createQuery(
                "SELECT JSON_GET_TEXT(dc.templateData, 'url') as url
                 FROM Sulu\\Page\\Domain\\Model\\Page p
                 JOIN p.dimensionContents dc
                 WHERE dc.templateKey = 'event'
                   AND dc.stage = 'draft'
                   AND JSON_GET_TEXT(dc.templateData, 'featured') = 'true'
                   AND JSON_GET_TEXT(dc.templateData, 'begin_date') >= :today
                 ORDER BY JSON_GET_TEXT(dc.templateData, 'begin_date') ASC",
            )
            ->setParameter('today', now()->format('Y-m-d'))
            ->setMaxResults(1)
            ->getOneOrNullResult();

        return $result['url'] ?? null;
    }

    /**
     * @return array<int, array{title: string, url: string, begin_date: string, end_date: string, main_media: object|null}>
     */
    public function getNextListEvents(int $limit = 3, ?string $excludeUrl = null): array
    {
        $dql = "SELECT JSON_GET_TEXT(dc.templateData, 'title') as title,
                       JSON_GET_TEXT(dc.templateData, 'url') as url,
                       JSON_GET_TEXT(dc.templateData, 'begin_date') as begin_date,
                       JSON_GET_TEXT(dc.templateData, 'end_date') as end_date,
                       JSON_GET_TEXT(dc.templateData, 'main_media') as main_media_json
                FROM Sulu\\Page\\Domain\\Model\\Page p
                JOIN p.dimensionContents dc
                WHERE dc.templateKey = 'event'
                  AND dc.stage = 'live'
                  AND JSON_GET_TEXT(dc.templateData, 'begin_date') >= :today";

        if (null !== $excludeUrl) {
            $dql .= " AND JSON_GET_TEXT(dc.templateData, 'url') != :excludeUrl";
        }

        $dql .= ' ORDER BY JSON_GET_TEXT(dc.templateData, \'begin_date\') ASC';

        $query = $this->entityManager
            ->createQuery($dql)
            ->setParameter('today', now()->format('Y-m-d'))
            ->setMaxResults($limit);

        if (null !== $excludeUrl) {
            $query->setParameter('excludeUrl', $excludeUrl);
        }

        $rows = $query->getArrayResult();

        return array_map(function (array $row): array {
            $mainMedia = null;

            if (isset($row['main_media_json'])) {
                /** @var array{id?: int}|null $mediaData */
                $mediaData = json_decode($row['main_media_json'], true);

                if (isset($mediaData['id'])) {
                    try {
                        $mainMedia = $this->mediaManager->getById($mediaData['id'], 'fr');
                    } catch (\Exception) {
                        // media not found, leave null
                    }
                }
            }

            return [
                'title' => $row['title'],
                'url' => $row['url'],
                'begin_date' => $row['begin_date'],
                'end_date' => $row['end_date'],
                'main_media' => $mainMedia,
            ];
        }, $rows);
    }
}

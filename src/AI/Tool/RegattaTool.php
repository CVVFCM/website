<?php

declare(strict_types=1);

namespace App\AI\Tool;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

use function Symfony\Component\Clock\now;

/**
 * Forgie's window on the club's regattas: published Sulu "event" pages with
 * event_type=regatta, split between past and upcoming (12-month window each way).
 *
 * @phpstan-type Regatta array{
 *     titre: string,
 *     debut: string,
 *     fin: string|null,
 *     url: string|null,
 *     lieu: string|null,
 *     description: string|null,
 *     series: list<array{serie: string, grade: string, prix_inscription: string|null}>,
 *     informations: string|null,
 * }
 */
#[AsTool('upcoming_regattas', 'Liste les régates à venir du club dans les 12 prochains mois, de la plus proche à la plus lointaine (titre, dates, lieu, séries et grades, tarif d\'inscription, informations).', method: 'upcoming')]
#[AsTool('past_regattas', 'Liste les régates passées du club dans les 12 derniers mois, de la plus récente à la plus ancienne (titre, dates, lieu, séries et grades, informations).', method: 'past')]
final readonly class RegattaTool
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{periode: string, regates: list<Regatta>}
     */
    public function upcoming(): array
    {
        return [
            'periode' => 'à venir (12 prochains mois)',
            'regates' => $this->fetch(now(), now()->modify('+1 year'), 'ASC'),
        ];
    }

    /**
     * @return array{periode: string, regates: list<Regatta>}
     */
    public function past(): array
    {
        return [
            'periode' => 'passées (12 derniers mois)',
            'regates' => $this->fetch(now()->modify('-1 year'), now(), 'DESC'),
        ];
    }

    /**
     * @return list<Regatta>
     */
    private function fetch(\DateTimeImmutable $from, \DateTimeImmutable $to, string $order): array
    {
        /** @var list<array{templateData: array<string, mixed>}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('dc.templateData')
            ->addSelect("JSON_GET_TEXT(dc.templateData, 'begin_date') AS HIDDEN begin_date")
            ->from(PageDimensionContent::class, 'dc')
            ->where('dc.locale = :locale')
            ->andWhere('dc.stage = :stage')
            ->andWhere('dc.templateKey = :templateKey')
            ->andWhere("JSON_GET_TEXT(dc.templateData, 'event_type') = :eventType")
            ->andWhere("JSON_GET_TEXT(dc.templateData, 'begin_date') >= :from")
            ->andWhere("JSON_GET_TEXT(dc.templateData, 'begin_date') < :to")
            ->orderBy('begin_date', $order)
            ->setParameter('locale', 'fr')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('templateKey', 'event')
            ->setParameter('eventType', 'regatta')
            ->setParameter('from', $from->format('Y-m-d\TH:i:s'))
            ->setParameter('to', $to->format('Y-m-d\TH:i:s'))
            ->getQuery()
            ->getArrayResult();

        return array_map(
            fn (array $row): array => $this->mapRegatta($row['templateData']),
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return Regatta
     */
    private function mapRegatta(array $data): array
    {
        $location = null;
        if (\is_array($data['location'] ?? null)) {
            $parts = [];
            /** @var mixed $part */
            foreach ([$data['location']['title'] ?? null, $data['location']['town'] ?? null] as $part) {
                if (\is_string($part) && '' !== trim($part)) {
                    $parts[] = trim($part);
                }
            }
            $location = [] !== $parts ? implode(', ', $parts) : null;
        }

        $series = [];
        /** @var mixed $serie */
        foreach ((array) ($data['series'] ?? []) as $serie) {
            if (\is_array($serie) && isset($serie['series'], $serie['rank'])) {
                $series[] = [
                    'serie' => (string) $serie['series'],
                    'grade' => (string) $serie['rank'],
                    'prix_inscription' => isset($serie['registration_price']) ? (string) $serie['registration_price'] : null,
                ];
            }
        }

        return [
            'titre' => (string) ($data['title'] ?? ''),
            'debut' => (string) ($data['begin_date'] ?? ''),
            'fin' => isset($data['end_date']) ? (string) $data['end_date'] : null,
            'url' => isset($data['url']) ? (string) $data['url'] : null,
            'lieu' => $location,
            'description' => $this->plainText($data['description'] ?? null),
            'series' => $series,
            'informations' => $this->plainText($data['regatta_informations'] ?? null),
        ];
    }

    private function plainText(mixed $html): ?string
    {
        if (!\is_string($html)) {
            return null;
        }

        $text = trim(strip_tags($html));

        return '' !== $text ? $text : null;
    }
}

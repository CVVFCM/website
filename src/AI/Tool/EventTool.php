<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\PageContentRepository;
use App\AI\TemplateData;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

use function Symfony\Component\Clock\now;

/**
 * Calendar overview of ALL the club's events (any type), 12-month window each way.
 * Details for régates / sorties en mer live in their dedicated tools.
 */
#[AsTool('events', 'Vue calendrier de tous les événements du club (12 mois en arrière ou en avant) avec leur type (Événement, Régate, Sortie en mer, Stage), dates, lieu et lien. Pour les détails d\'une régate ou d\'une sortie en mer, utiliser les outils dédiés.')]
final readonly class EventTool
{
    private const array TYPES = [
        'default' => 'Événement',
        'regatta' => 'Régate',
        'sea_trip' => 'Sortie en mer',
        'stage' => 'Stage',
    ];

    public function __construct(
        private PageContentRepository $pageContentRepository,
    ) {
    }

    /**
     * @return array{periode: string, evenements: list<array<string, mixed>>}
     */
    public function __invoke(
        #[Schema(description: 'Période : événements à venir ou passés. Par défaut : à venir.', enum: ['a_venir', 'passees'])]
        string $periode = 'a_venir',
    ): array {
        $upcoming = 'passees' !== $periode;

        $data = $this->pageContentRepository->findEvents(
            null,
            $upcoming ? now() : now()->modify('-1 year'),
            $upcoming ? now()->modify('+1 year') : now(),
            $upcoming ? 'ASC' : 'DESC',
        );

        return [
            'periode' => $upcoming ? 'à venir (12 prochains mois)' : 'passés (12 derniers mois)',
            'evenements' => array_map(
                static fn (array $event): array => [
                    'titre' => (string) ($event['title'] ?? ''),
                    'type' => self::TYPES[\is_string($event['event_type'] ?? null) ? (string) $event['event_type'] : 'default'] ?? self::TYPES['default'],
                    'debut' => (string) ($event['begin_date'] ?? ''),
                    'fin' => isset($event['end_date']) ? (string) $event['end_date'] : null,
                    'lieu' => TemplateData::location($event),
                    'url' => isset($event['url']) ? (string) $event['url'] : null,
                    'description' => TemplateData::plainText($event['description'] ?? null),
                ],
                $data,
            ),
        ];
    }
}

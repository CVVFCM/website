<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\ContactResolver;
use App\AI\PageContentRepository;
use App\AI\TemplateData;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

use function Symfony\Component\Clock\now;

/**
 * The club's "sorties en mer" (sea trips): published Sulu event pages with
 * event_type=sea_trip. 24-month window each way — sea trips are rare and often
 * planned far ahead (fixtures/data sit 13+ months out).
 */
#[AsTool('sea_trips', 'Liste les sorties en mer du club (24 mois en arrière ou en avant) : titre, dates, lieu, description, bateaux (type, chef de bord, places, prix approximatif par personne), liens et contacts.')]
final readonly class SeaTripTool
{
    public function __construct(
        private PageContentRepository $pageContentRepository,
        private ContactResolver $contactResolver,
    ) {
    }

    /**
     * @return array{periode: string, sorties: list<array<string, mixed>>}
     */
    public function __invoke(
        #[Schema(description: 'Période : sorties à venir ou passées. Par défaut : à venir.', enum: ['a_venir', 'passees'])]
        string $periode = 'a_venir',
    ): array {
        $upcoming = 'passees' !== $periode;

        $data = $this->pageContentRepository->findEvents(
            'sea_trip',
            $upcoming ? now() : now()->modify('-2 years'),
            $upcoming ? now()->modify('+2 years') : now(),
            $upcoming ? 'ASC' : 'DESC',
        );

        return [
            'periode' => $upcoming ? 'à venir (24 prochains mois)' : 'passées (24 derniers mois)',
            'sorties' => array_map(fn (array $trip): array => $this->mapTrip($trip), $data),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function mapTrip(array $data): array
    {
        $boats = [];
        /** @var mixed $boat */
        foreach ((array) ($data['boats'] ?? []) as $boat) {
            if (\is_array($boat) && isset($boat['boat_type'])) {
                $boats[] = [
                    'type_bateau' => (string) $boat['boat_type'],
                    'chef_de_bord' => $this->contactResolver->resolve($boat['captain'] ?? null),
                    'places' => isset($boat['available_seats']) ? (string) $boat['available_seats'] : null,
                    'prix_approximatif_par_personne' => isset($boat['approximative_price']) ? (string) $boat['approximative_price'] : null,
                ];
            }
        }

        return [
            'titre' => (string) ($data['title'] ?? ''),
            'debut' => (string) ($data['begin_date'] ?? ''),
            'fin' => isset($data['end_date']) ? (string) $data['end_date'] : null,
            'url' => isset($data['url']) ? (string) $data['url'] : null,
            'lieu' => TemplateData::location($data),
            'description' => TemplateData::plainText($data['description'] ?? null),
            'bateaux' => $boats,
            'liens' => TemplateData::links($data),
            'contacts' => $this->contactResolver->resolve($data['contact'] ?? null),
        ];
    }
}

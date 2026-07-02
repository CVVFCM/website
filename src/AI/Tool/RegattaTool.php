<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\ContactResolver;
use App\AI\PageContentRepository;
use App\AI\TemplateData;
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
 *     services: list<array{nom: string, disponible: bool}>,
 *     liens: list<array{titre: string, url: string}>,
 *     contacts: list<string>,
 * }
 */
#[AsTool('upcoming_regattas', 'Liste les régates à venir du club dans les 12 prochains mois, de la plus proche à la plus lointaine (titre, dates, lieu, séries et grades, tarif d\'inscription, services sur place, liens, contacts, informations).', method: 'upcoming')]
#[AsTool('past_regattas', 'Liste les régates passées du club dans les 12 derniers mois, de la plus récente à la plus ancienne (titre, dates, lieu, séries et grades, services sur place, liens, contacts, informations).', method: 'past')]
final readonly class RegattaTool
{
    public function __construct(
        private PageContentRepository $pageContentRepository,
        private ContactResolver $contactResolver,
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
        return array_map(
            fn (array $data): array => $this->mapRegatta($data),
            $this->pageContentRepository->findEvents('regatta', $from, $to, $order),
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return Regatta
     */
    private function mapRegatta(array $data): array
    {
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

        $services = [];
        /** @var mixed $service */
        foreach ((array) ($data['services'] ?? []) as $service) {
            if (\is_array($service) && isset($service['name'])) {
                $services[] = [
                    'nom' => (string) $service['name'],
                    'disponible' => (bool) ($service['availability'] ?? false),
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
            'series' => $series,
            'informations' => TemplateData::plainText($data['regatta_informations'] ?? null),
            'services' => $services,
            'liens' => TemplateData::links($data),
            'contacts' => $this->contactResolver->resolve($data['contact'] ?? null),
        ];
    }
}

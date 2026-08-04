<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\LegalDocumentRepository;
use App\AI\TemplateData;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

#[AsTool('club_rules', 'Le texte complet d\'un document officiel du club : statuts de l\'association, règlement intérieur, ou règlement de navigation du lac (arrêté préfectoral : zones interdites, activités autorisées, règles de route…).')]
final readonly class LegalDocumentTool
{
    public function __construct(
        private LegalDocumentRepository $legalDocumentRepository,
    ) {
    }

    /**
     * @return array{document: ?string, reference: ?string, contenu: ?string}|array{erreur: string}
     */
    public function __invoke(
        #[Schema(description: 'Le document à consulter.', enum: ['statuts', 'reglement_interieur', 'reglement_lac'])]
        string $document,
    ): array {
        $data = $this->legalDocumentRepository->findByDocumentKey($document);

        if (null === $data) {
            return ['erreur' => 'Document introuvable.'];
        }

        return [
            'document' => TemplateData::plainText($data['title'] ?? null),
            'reference' => TemplateData::plainText($data['reference'] ?? null),
            'contenu' => TemplateData::plainText($data['content'] ?? null),
        ];
    }
}

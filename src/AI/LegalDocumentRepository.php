<?php

declare(strict_types=1);

namespace App\AI;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Snippet\Domain\Model\SnippetDimensionContent;

/**
 * The club's official documents, stored as published legal_document snippets
 * (see config/templates/snippets/legal_document.xml) and read by Forgie.
 */
final readonly class LegalDocumentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByDocumentKey(string $documentKey): ?array
    {
        /** @var list<array{templateData: array<string, mixed>}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('dc.templateData')
            ->from(SnippetDimensionContent::class, 'dc')
            ->where('dc.locale = :locale')
            ->andWhere('dc.stage = :stage')
            ->andWhere('dc.templateKey = :templateKey')
            ->andWhere("JSON_GET_TEXT(dc.templateData, 'document_key') = :documentKey")
            ->setParameter('locale', 'fr')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('templateKey', 'legal_document')
            ->setParameter('documentKey', $documentKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return $rows[0]['templateData'] ?? null;
    }
}

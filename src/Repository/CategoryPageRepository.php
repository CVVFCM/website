<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\Page;

/**
 * Resolves the page a category link should point to, since category pages
 * themselves are not accessible (they 404, see CategoryController).
 */
final readonly class CategoryPageRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Route slug of the first published, non-category page (tree order) inside
     * the category's subtree, or null when the category has no such descendant.
     */
    public function findFirstPageSlug(string $categoryUuid, string $locale): ?string
    {
        /** @var array{slug: string}|null $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select('route.slug AS slug')
            ->from(Page::class, 'page')
            ->innerJoin('page.dimensionContents', 'dc')
            ->innerJoin('dc.route', 'route')
            ->innerJoin(Page::class, 'ancestor', Join::WITH, 'page.lft > ancestor.lft AND page.rgt < ancestor.rgt')
            ->andWhere('ancestor.uuid = :uuid')
            ->andWhere('dc.locale = :locale')
            ->andWhere('dc.stage = :stage')
            ->andWhere('dc.version = :version')
            ->andWhere('dc.templateKey != :categoryTemplateKey')
            ->orderBy('page.lft', 'ASC')
            ->setMaxResults(1)
            ->setParameter('uuid', $categoryUuid)
            ->setParameter('locale', $locale)
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('categoryTemplateKey', 'category')
            ->getQuery()
            ->getOneOrNullResult();

        return $row['slug'] ?? null;
    }
}

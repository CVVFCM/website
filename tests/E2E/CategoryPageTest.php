<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Repository\CategoryPageRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CategoryPageTest extends WebTestCase
{
    public function testCategoryUrlReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ecole-de-voile');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testNestedCategoryUrlReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ecole-de-voile/optimist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testChildPageStillAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ecole-de-voile/optimist/premiers-bords');

        $this->assertResponseIsSuccessful();
    }

    public function testBreadcrumbCategoryLinksTargetFirstPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ecole-de-voile/optimist/virement-de-bord');

        $this->assertResponseIsSuccessful();

        $breadcrumbLinks = $crawler->filter('.BreadCrumb a');
        $hrefs = $breadcrumbLinks->each(static fn ($node) => $node->attr('href'));

        // Both category ancestors must link to the first page of their subtree, not to themselves.
        $this->assertContains('/ecole-de-voile/optimist/premiers-bords', $hrefs);
        $this->assertNotContains('/ecole-de-voile', $hrefs);
        $this->assertNotContains('/ecole-de-voile/optimist', $hrefs);

        // The link must resolve directly (no redirect): the test client does not follow
        // redirects, so a 30x here would fail the success assertion.
        $link = $breadcrumbLinks->reduce(
            static fn ($node): bool => '/ecole-de-voile/optimist/premiers-bords' === $node->attr('href'),
        )->first()->link();
        $client->click($link);
        $this->assertResponseIsSuccessful();
        $this->assertFalse($client->getResponse()->isRedirection());
    }

    public function testMainNavigationCategoryItemsLinkToFirstPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        // Category items in the main navigation must be links targeting their first page.
        $categoryLinks = $crawler->filter('.MainNavigation__item--category > a.MainNavigation__link');
        $this->assertGreaterThan(0, $categoryLinks->count());

        $hrefs = $categoryLinks->each(static fn ($node) => $node->attr('href'));
        $this->assertContains('/ecole-de-voile/optimist/premiers-bords', $hrefs);
    }

    public function testRepositoryResolvesFirstPageSlugInTreeOrder(): void
    {
        static::bootKernel();
        /** @var CategoryPageRepository $repository */
        $repository = static::getContainer()->get(CategoryPageRepository::class);

        $categoryUuid = $this->findPageUuidBySlug('/ecole-de-voile');
        $this->assertSame(
            '/ecole-de-voile/optimist/premiers-bords',
            $repository->findFirstPageSlug($categoryUuid, 'fr'),
        );

        // A leaf page has no descendants: no slug must be returned.
        $leafUuid = $this->findPageUuidBySlug('/ecole-de-voile/optimist/premiers-bords');
        $this->assertNull($repository->findFirstPageSlug($leafUuid, 'fr'));
    }

    private function findPageUuidBySlug(string $slug): string
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var array{uuid: string} $row */
        $row = $entityManager->createQueryBuilder()
            ->select('page.uuid AS uuid')
            ->from(\Sulu\Page\Domain\Model\Page::class, 'page')
            ->innerJoin('page.dimensionContents', 'dc')
            ->innerJoin('dc.route', 'route')
            ->andWhere('route.slug = :slug')
            ->andWhere('dc.locale = :locale')
            ->andWhere('dc.stage = :stage')
            ->setMaxResults(1)
            ->setParameter('slug', $slug)
            ->setParameter('locale', 'fr')
            ->setParameter('stage', \Sulu\Content\Domain\Model\DimensionContentInterface::STAGE_LIVE)
            ->getQuery()
            ->getSingleResult();

        return $row['uuid'];
    }
}

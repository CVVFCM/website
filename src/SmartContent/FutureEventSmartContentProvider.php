<?php

namespace App\SmartContent;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\Builder;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancer;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Infrastructure\Sulu\Content\PageSmartContentProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use function Symfony\Component\Clock\now;

#[AutoconfigureTag('sulu_content.smart_content_provider', ['type' => 'future_event'])]
final readonly class FutureEventSmartContentProvider extends PageSmartContentProvider
{
    /**
     * @param array<string, mixed> $bundles
     */
    public function __construct(
        DimensionContentQueryEnhancer $dimensionContentQueryEnhancer,
        #[Autowire('@sulu_admin.form_metadata_provider')]
        MetadataProviderInterface $formMetadataProvider,
        #[Autowire('@sulu_admin.smart_content_query_enhancer')]
        SmartContentQueryEnhancer $smartContentQueryEnhancer,
        ?TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.bundles%')]
        array $bundles,
        WebspaceManagerInterface $webspaceManager,
        #[Autowire('@sulu_security.access_control_query_enhancer')]
        AccessControlQueryEnhancer $accessControlQueryEnhancer,
        ?Security $security,
        ?array $permissions = null,
    ) {
        parent::__construct(
            $dimensionContentQueryEnhancer,
            $formMetadataProvider,
            $smartContentQueryEnhancer,
            $tokenStorage,
            $entityManager,
            $bundles,
            $webspaceManager,
            $accessControlQueryEnhancer,
            $security,
            $permissions,
        );
    }

    #[\Override]
    public function getConfiguration(): ProviderConfigurationInterface
    {
        return Builder::create()
            ->enableTags()
            ->enableCategories()
            ->enableLimit()
            ->enablePagination()
            ->enablePresentAs()
            ->enableTypes()
            ->enableDatasource(PageInterface::RESOURCE_KEY, PageInterface::RESOURCE_KEY, 'column_list')
            ->getConfiguration();
    }

    #[\Override]
    public function getType(): string
    {
        return 'future_event';
    }

    #[\Override]
    protected function addInternalFilters(QueryBuilder $queryBuilder, array $filters, string $alias): void
    {
        $queryBuilder
            ->andWhere('filterDimensionContent.templateKey = :template_key')
            ->andWhere('JSON_GET_TEXT(filterDimensionContent.templateData, \'begin_date\') >= :current_date')
            ->setParameter('current_date', now()->format('Y-m-d'))
            ->setParameter('template_key', 'event');

        parent::addInternalFilters($queryBuilder, $filters, $alias);
    }
}

<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\FacebookToken;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

final class FacebookTokenAdmin extends Admin
{
    public const string FACEBOOK_TOKEN_LIST_VIEW = 'app.facebook_tokens_list';

    public function __construct(private readonly ViewBuilderFactoryInterface $viewBuilderFactory)
    {
    }

    #[\Override]
    public function configureViews(ViewCollection $viewCollection): void
    {
        $listView = $this->viewBuilderFactory
            ->createListViewBuilder(self::FACEBOOK_TOKEN_LIST_VIEW, '/facebook_tokens')
            ->setResourceKey(FacebookToken::RESOURCE_KEY)
            ->setListKey('facebook_tokens')
            ->addListAdapters(['table']);

        $listView->addToolbarActions(
            [
                new ToolbarAction('app.facebook_tokens.get_new'),
            ]
        );

        $viewCollection->add($listView);
    }

    #[\Override]
    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        $facebookTokensNavigationItem = new NavigationItem('Jetons Facebook');
        $facebookTokensNavigationItem->setView(self::FACEBOOK_TOKEN_LIST_VIEW);
        $facebookTokensNavigationItem->setIcon('fa-facebook-square');
        $facebookTokensNavigationItem->setPosition(30);

        $navigationItemCollection->add($facebookTokensNavigationItem);
    }
}

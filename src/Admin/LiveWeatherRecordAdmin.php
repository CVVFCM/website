<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\LiveWeatherRecord;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

final class LiveWeatherRecordAdmin extends Admin
{
    public const string LIVE_WEATHER_RECORD_LIST_VIEW = 'app.live_weather_records_list';

    public function __construct(private readonly ViewBuilderFactoryInterface $viewBuilderFactory)
    {
    }

    #[\Override]
    public function configureViews(ViewCollection $viewCollection): void
    {
        // Read-only datagrid: no toolbar actions, no edit view.
        $listView = $this->viewBuilderFactory
            ->createListViewBuilder(self::LIVE_WEATHER_RECORD_LIST_VIEW, '/live-weather-records')
            ->setResourceKey(LiveWeatherRecord::RESOURCE_KEY)
            ->setListKey('live_weather_records')
            ->addListAdapters(['table']);

        $viewCollection->add($listView);
    }

    #[\Override]
    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        $navigationItem = new NavigationItem('Météo maison');
        $navigationItem->setView(self::LIVE_WEATHER_RECORD_LIST_VIEW);
        $navigationItem->setIcon('fa-wind');
        $navigationItem->setPosition(31);

        $navigationItemCollection->add($navigationItem);
    }
}

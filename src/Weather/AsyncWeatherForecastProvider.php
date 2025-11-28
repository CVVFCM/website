<?php

namespace App\Weather;

use App\DTO\WeatherForecast;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsDecorator(WeatherForecastProvider::class)]
final readonly class AsyncWeatherForecastProvider implements WeatherForecastProvider
{
    private const string WEATHER_FORECAST_CACHE_KEY = 'weather_forecast';

    public function __construct(
        private HubInterface $hub,
        private CacheItemPoolInterface $cacheItemPool,
        private WeatherForecastProvider $decorated,
        private LockFactory $lockFactory,
        private int $cacheTtl = 3600,
    ) {
    }

    #[\Override]
    public function get(): array
    {
        /** @var list<WeatherForecast> $result */
        $result = $this->cacheItemPool->getItem(self::WEATHER_FORECAST_CACHE_KEY)->get() ?? [];

        return $result;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onKernelTerminate(): void
    {
        if (
            $this->cacheItemPool->hasItem(self::WEATHER_FORECAST_CACHE_KEY)
            && $this->cacheItemPool->getItem(self::WEATHER_FORECAST_CACHE_KEY)->isHit()
        ) {
            return;
        }

        $lock = $this->lockFactory->createLock(self::WEATHER_FORECAST_CACHE_KEY);
        if (!$lock->acquire()) {
            return;
        }

        $weatherForecast = $this->decorated->get();
        $item = $this->cacheItemPool->getItem(self::WEATHER_FORECAST_CACHE_KEY);
        $item->set($weatherForecast);
        $item->expiresAfter($this->cacheTtl);
        $this->cacheItemPool->save($item);

        $json = json_encode(['type' => '/weather/forecast', 'data' => $weatherForecast]);
        assert(is_string($json));
        $update = new Update('/weather/forecast', $json);
        $this->hub->publish($update);

        $lock->release();
    }
}

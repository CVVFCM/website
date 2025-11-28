<?php

namespace App\Weather;

use App\DTO\LiveWeather;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsDecorator(LiveWeatherProvider::class)]
final readonly class AsyncLiveWeatherProvider implements LiveWeatherProvider
{
    private const string LIVE_WEATHER_CACHE_KEY = 'live_weather';

    public function __construct(
        private CacheItemPoolInterface $liveWeatherCache,
        private LiveWeatherProvider $decorated,
        private LockFactory $lockFactory,
        private HubInterface $mercureHub,
        private int $cacheTtl = 360,
    ) {
    }

    #[\Override]
    public function get(): ?LiveWeather
    {
        $result = $this->liveWeatherCache->getItem(self::LIVE_WEATHER_CACHE_KEY)->get();
        assert($result instanceof LiveWeather || null === $result);

        return $result;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onKernelTerminate(): void
    {
        $item = $this->liveWeatherCache->getItem(self::LIVE_WEATHER_CACHE_KEY);
        $liveWeather = $item->get();
        assert($liveWeather instanceof LiveWeather || null === $liveWeather);
        if (
            $liveWeather instanceof LiveWeather
            && $liveWeather->updatedAt > new \DateTimeImmutable('-'.$this->cacheTtl.' seconds')
        ) {
            return;
        }

        $lock = $this->lockFactory->createLock(self::LIVE_WEATHER_CACHE_KEY, 10.0);
        if (!$lock->acquire()) {
            return;
        }

        $liveWeather = $this->decorated->get();

        $item->set($liveWeather);

        $this->liveWeatherCache->save($item);

        $json = json_encode(['type' => '/weather/live', 'data' => $liveWeather]);
        assert(is_string($json));
        $this->mercureHub->publish(new Update('/weather/live', $json));

        $lock->release();
    }

    #[\Override]
    public function getExternalLink(): string
    {
        return $this->decorated->getExternalLink();
    }
}

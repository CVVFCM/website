<?php

namespace App\Twig\Components;

use App\DTO\LiveWeather;
use App\Weather\LiveWeatherProvider;
use Psr\Log\LoggerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class WeatherLive
{
    use DefaultActionTrait;

    #[LiveProp]
    public bool $full = false;

    public function __construct(
        private readonly LiveWeatherProvider $weatherProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getLink(): string
    {
        return $this->weatherProvider->getExternalLink();
    }

    public function getLiveWeather(): ?LiveWeather
    {
        try {
            return $this->weatherProvider->get();
        } catch (\Exception $e) {
            $this->logger->error('Error when fetching live weather', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

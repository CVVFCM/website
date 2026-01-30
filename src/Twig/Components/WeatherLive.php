<?php

namespace App\Twig\Components;

use App\DTO\LiveWeather;
use App\Weather\LiveWeatherProvider;
use Psr\Log\LoggerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class WeatherLive
{
    public bool $full = false;
    public string $variant = 'default';

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

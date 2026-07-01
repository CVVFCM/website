<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\LiveWeather;
use App\Weather\LiveWeatherProvider;
use App\Weather\WeatherForecastProvider;
use Psr\Log\LoggerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class WeatherLive
{
    public bool $full = false;
    public string $variant = 'default';

    public function __construct(
        private readonly LiveWeatherProvider $weatherProvider,
        private readonly WeatherForecastProvider $weatherForecastProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getLink(): string
    {
        return $this->weatherProvider->getExternalLink();
    }

    /**
     * Current sky condition (French label). The live station has no sky data, so this comes from the
     * forecast's current-hour WMO code. Null when no matching forecast is available.
     */
    public function getCurrentCondition(): ?string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        foreach ($this->weatherForecastProvider->get() as $forecast) {
            if ($forecast->date->format('Y-m-d') === $now->format('Y-m-d')
                && $forecast->date->format('G') === $now->format('G')) {
                return $forecast->getConditionLabel();
            }
        }

        return null;
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

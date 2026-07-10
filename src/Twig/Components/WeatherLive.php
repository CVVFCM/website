<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\LiveWeather;
use App\Repository\LiveWeatherRecordRepository;
use App\Repository\WeatherForecastRecordRepository;
use App\Weather\LiveWeatherProvider;
use Psr\Log\LoggerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class WeatherLive
{
    // Homepage variants only display instantaneous values (temperature, wind) that the DB persists, so
    // they read the stored records; the richer live-page variants (min/max/average/rain) keep using the
    // live station provider.
    private const array DB_BACKED_VARIANTS = ['homepage', 'homepage-wind'];

    public bool $full = false;
    public string $variant = 'default';

    public function __construct(
        private readonly LiveWeatherProvider $weatherProvider,
        private readonly LiveWeatherRecordRepository $liveWeatherRepository,
        private readonly WeatherForecastRecordRepository $forecastRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getLink(): string
    {
        return $this->weatherProvider->getExternalLink();
    }

    /**
     * Current sky condition (French label). The live station has no sky data, so this comes from the
     * nearest persisted forecast's WMO code. Null when no matching forecast is available.
     */
    public function getCurrentCondition(): ?string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        return $this->forecastRepository->findNearest($now)?->toWeatherForecast()->getConditionLabel();
    }

    public function getLiveWeather(): ?LiveWeather
    {
        if (\in_array($this->variant, self::DB_BACKED_VARIANTS, true)) {
            return $this->liveWeatherRepository->findLatest()?->toLiveWeather();
        }

        try {
            return $this->weatherProvider->get();
        } catch (\Exception $e) {
            $this->logger->error('Error when fetching live weather', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

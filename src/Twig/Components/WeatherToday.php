<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\WeatherForecast;
use App\Weather\WeatherForecastProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final readonly class WeatherToday
{
    /**
     * Forecast hour (Europe/Paris) shown for each prevision row.
     */
    private const array SLOTS = [
        'Matin' => '10',
        'Aprem' => '15',
    ];

    public function __construct(
        private WeatherForecastProvider $weatherForecastProvider,
    ) {
    }

    /**
     * Morning + afternoon forecast for the relevant day. After 19h (Europe/Paris) the day rolls over
     * to tomorrow. A slot is null when the API has no matching forecast.
     *
     * @return array<string, WeatherForecast|null>
     */
    public function getPrevisions(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $target = ((int) $now->format('G') >= 19 ? $now->modify('+1 day') : $now)->format('Y-m-d');

        $forecasts = $this->weatherForecastProvider->get();

        $previsions = [];
        foreach (self::SLOTS as $label => $hour) {
            $previsions[$label] = $this->findForecast($forecasts, $target, $hour);
        }

        return $previsions;
    }

    /**
     * @param list<WeatherForecast> $forecasts
     */
    private function findForecast(array $forecasts, string $date, string $hour): ?WeatherForecast
    {
        foreach ($forecasts as $forecast) {
            if ($forecast->date->format('Y-m-d') === $date && $forecast->date->format('G') === $hour) {
                return $forecast;
            }
        }

        return null;
    }
}

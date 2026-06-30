<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\WeatherForecast;
use App\Weather\WeatherForecastProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class WeatherToday
{
    public function __construct(
        private WeatherForecastProvider $weatherForecastProvider,
    ) {
    }

    public function getData(): array
    {
        return array_filter(
            $this->weatherForecastProvider->get(),
            fn (WeatherForecast $forecast): bool => array_key_exists($forecast->date->format('H'), WeatherForecast::HOURS),
        );
    }
}

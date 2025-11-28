<?php

namespace App\Twig\Components;

use App\Weather\WeatherForecastProvider;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class WeatherToday
{
    use DefaultActionTrait;

    public function __construct(
        private WeatherForecastProvider $weatherForecastProvider,
    ) {
    }

    public function getData(): array
    {
        return $this->weatherForecastProvider->get();
    }
}

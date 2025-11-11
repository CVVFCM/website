<?php

namespace App\Twig\Components;

use App\DTO\LiveWeather;
use App\Weather\LiveWeatherProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class WeatherLive
{
    public function __construct(private readonly LiveWeatherProvider $weatherProvider)
    {
    }

    public function getLiveWeather(): LiveWeather
    {
        return $this->weatherProvider->get();
    }
}

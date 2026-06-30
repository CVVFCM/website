<?php

declare(strict_types=1);

namespace App\Weather;

use App\DTO\LiveWeather;

interface LiveWeatherProvider
{
    public function get(): ?LiveWeather;

    public function getExternalLink(): string;
}

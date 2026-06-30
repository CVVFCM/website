<?php

declare(strict_types=1);

namespace App\Weather;

use App\DTO\WeatherForecast;

interface WeatherForecastProvider
{
    /**
     * @return list<WeatherForecast>
     */
    public function get(): array;
}

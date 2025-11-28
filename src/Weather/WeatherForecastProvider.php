<?php

namespace App\Weather;

use App\DTO\WeatherForecast;

interface WeatherForecastProvider
{
    /**
     * @return list<WeatherForecast>
     */
    public function get(): array;
}

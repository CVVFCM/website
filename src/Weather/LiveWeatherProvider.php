<?php

namespace App\Weather;

use App\DTO\LiveWeather;

interface LiveWeatherProvider
{
    public function get(): LiveWeather;
}

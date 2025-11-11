<?php

namespace App\DTO;

use App\Weather\CardinalDirection;

/**
 * @psalm-suppress MissingConstructor
 */
final class LiveWeather
{
    public \DateTimeImmutable $updatedAt;

    public float $humidity;

    public float $humidityMin;

    public float $humidityMax;

    public float $pressure;

    public float $pressureMin;

    public float $pressureMax;

    public float $rainRate;

    public float $rainTotal;

    public float $temperature;

    public float $temperatureMin;

    public float $temperatureMax;

    public int $windDirection;

    public float $windSpeed;

    public float $windGusts;

    public int $windDirectionAverage;

    public float $windSpeedAverage;

    public float $windSpeedMax;

    public float $windSpeedMin;

    public function getWindDirectionAsCardinal(): CardinalDirection
    {
        return CardinalDirection::fromDirection($this->windDirection);
    }

    public function getWindDirectionAverageAsCardinal(): CardinalDirection
    {
        return CardinalDirection::fromDirection($this->windDirectionAverage);
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

use App\Weather\CardinalDirection;

/**
 * @psalm-suppress MissingConstructor
 */
final class LiveWeather
{
    public \DateTimeImmutable $updatedAt;

    // The live station always reports these; they are nullable because the same object is also
    // rebuilt from a stored observation, and historical hours imported as wind-only means have no
    // temperature, humidity or pressure to give. Templates must skip the block rather than print a
    // zero, which would read as a genuine measurement.
    public ?float $humidity;

    public ?float $humidityMin;

    public ?float $humidityMax;

    public ?float $pressure;

    public ?float $pressureMin;

    public ?float $pressureMax;

    public float $rainRate;

    public float $rainTotal;

    public int $solarRadiation;

    public ?float $temperature;

    public ?float $temperatureMin;

    public ?float $temperatureMax;

    public int $windDirection;

    public float $windSpeed;

    public ?float $windGusts;

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

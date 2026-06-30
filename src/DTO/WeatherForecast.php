<?php

declare(strict_types=1);

namespace App\DTO;

use App\Weather\CardinalDirection;

final readonly class WeatherForecast
{
    public const array HOURS = [
        '10' => 'Matin',
        '13' => 'Midi',
        '16' => 'Après-midi',
    ];

    public function __construct(
        public \DateTimeImmutable $date,
        public float $temperature,
        public float $pressure,
        public int $humidity,
        public float $precipitation,
        public float $windSpeed,
        public int $windDirection,
    ) {
    }

    public function getLabel(): string
    {
        /** @var int<0,23> $hour */
        $hour = $this->date->format('H');

        if (array_key_exists($hour, self::HOURS)) {
            return self::HOURS[$hour];
        }

        return $this->date->format('H\hi');
    }

    public function getWindCardinalDirection(): CardinalDirection
    {
        return CardinalDirection::fromDirection($this->windDirection);
    }
}

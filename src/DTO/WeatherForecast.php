<?php

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
        public float $precipitation,
        public float $windSpeed,
        public int $windDirection,
    ) {
    }

    public function getLabel(): string
    {
        /** @var 10|13|16 $hour */
        $hour = $this->date->format('H');

        return self::HOURS[$hour] ?? 'En ce moment';
    }

    public function getWindCardinalDirection(): CardinalDirection
    {
        return CardinalDirection::fromDirection($this->windDirection);
    }
}

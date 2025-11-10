<?php

namespace App\DTO;

final readonly class WeatherForecast
{
    private const array CARDINALS = [
        0 => 'Nord',
        45 => 'Nord-Est',
        90 => 'Est',
        135 => 'Sud-Est',
        180 => 'Sud',
        225 => 'Sud-Ouest',
        270 => 'Ouest',
        315 => 'Nord-Ouest',
    ];

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

    public function getWindCardinalDirection(): string
    {
        foreach (self::CARDINALS as $direction => $cardinal) {
            $direction = (float) $direction;
            $lowerDirection = fmod($direction - 22.5 + 360., 360.);
            $upperDirection = fmod($direction + 22.5, 360.);

            if ($this->windDirection > $lowerDirection && $this->windDirection <= $upperDirection) {
                return $cardinal;
            }
        }

        return 'Variable';
    }
}

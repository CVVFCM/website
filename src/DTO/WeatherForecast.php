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

    /**
     * WMO weather interpretation codes → French labels.
     *
     * @see https://open-meteo.com/en/docs#weathervariables
     */
    private const array CONDITIONS = [
        0 => 'Ciel clair',
        1 => 'Éclaircies',
        2 => 'Éclaircies',
        3 => 'Couvert',
        45 => 'Brouillard',
        48 => 'Brouillard givrant',
        51 => 'Bruine',
        53 => 'Bruine',
        55 => 'Bruine',
        56 => 'Bruine verglaçante',
        57 => 'Bruine verglaçante',
        61 => 'Pluie',
        63 => 'Pluie',
        65 => 'Pluie',
        66 => 'Pluie verglaçante',
        67 => 'Pluie verglaçante',
        71 => 'Neige',
        73 => 'Neige',
        75 => 'Neige',
        77 => 'Neige',
        80 => 'Averses',
        81 => 'Averses',
        82 => 'Averses',
        85 => 'Averses de neige',
        86 => 'Averses de neige',
        95 => 'Orage',
        96 => 'Orage (grêle)',
        99 => 'Orage (grêle)',
    ];

    public function __construct(
        public \DateTimeImmutable $date,
        public float $temperature,
        public float $pressure,
        public int $humidity,
        public float $precipitation,
        public float $windSpeed,
        public int $windDirection,
        public int $weatherCode,
    ) {
    }

    public function getConditionLabel(): string
    {
        return self::CONDITIONS[$this->weatherCode] ?? '—';
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

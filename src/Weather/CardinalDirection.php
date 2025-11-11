<?php

namespace App\Weather;

enum CardinalDirection: string
{
    case N = 'Nord';
    case NE = 'Nord-Est';
    case E = 'Est';
    case SE = 'Sud-Est';
    case S = 'Sud';
    case SW = 'Sud-Ouest';
    case W = 'Ouest';
    case NW = 'Nord-Ouest';
    case VARIABLE = 'Variable';

    public function getDirection(): int
    {
        return match ($this) {
            self::N => 0,
            self::NE => 45,
            self::E => 90,
            self::SE => 135,
            self::S => 180,
            self::SW => 225,
            self::W => 270,
            self::NW => 315,
            default => -1,
        };
    }

    public function getMinDirection(): float
    {
        return (float) $this->getDirection() - 22.5;
    }

    public function getMaxDirection(): float
    {
        return (float) $this->getDirection() + 22.5;
    }

    public static function fromDirection(int $direction): CardinalDirection
    {
        $direction = (float) $direction;

        foreach (self::cases() as $case) {
            if (self::VARIABLE === $case) {
                continue;
            }

            $min = fmod($case->getMinDirection() + 360., 360.);
            $max = fmod($case->getMaxDirection(), 360.);

            if ($direction > $min && $direction <= $max) {
                return $case;
            }
        }

        return self::VARIABLE;
    }
}

<?php

declare(strict_types=1);

namespace App\Event;

/**
 * The event types available on the event page template, with their French labels.
 * Single source of truth for the type → label mapping (Twig, AI tools, …).
 */
enum EventType: string
{
    case Default = 'default';
    case Regatta = 'regatta';
    case SeaTrip = 'sea_trip';
    case Stage = 'stage';
    case Efv = 'efv';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Événement',
            self::Regatta => 'Régate',
            self::SeaTrip => 'Sortie en mer',
            self::Stage => 'Stage',
            self::Efv => 'EFV',
        };
    }

    /**
     * Label for a raw stored value, falling back to « Événement » for null or unknown types.
     */
    public static function labelFor(?string $type): string
    {
        return (null !== $type ? self::tryFrom($type) ?? self::Default : self::Default)->label();
    }
}

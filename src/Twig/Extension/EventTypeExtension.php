<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Event\EventType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class EventTypeExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('event_type_label', EventType::labelFor(...)),
        ];
    }
}

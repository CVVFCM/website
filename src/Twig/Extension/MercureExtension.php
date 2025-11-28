<?php

namespace App\Twig\Extension;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MercureExtension extends AbstractExtension
{
    public function __construct(
        private readonly HubInterface $hub,
    )
    {
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('mercure_last_event_id', $this->getMercureLastEventId(...)),
        ];
    }

    public function getMercureLastEventId(): string
    {
        return $this->hub->publish(new Update('/mercure/last-event-id', ''));
    }
}

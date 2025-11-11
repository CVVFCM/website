<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Webcam
{
    public ?string $url = null;

    /**
     * @var array{url: string, title: string, description: string}|null
     */
    public ?array $link = null;
}

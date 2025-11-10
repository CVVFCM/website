<?php

namespace App\Twig\Components;

use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;
use Symfony\UX\Map\Map as UXMap;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Map
{
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?int $zoom = 15;
    public ?string $title = null;

    public function getMap(): UXMap
    {
        if (null === $this->latitude || null === $this->longitude) {
            throw new \LogicException('Latitude and Longitude must be set to create a map.');
        }

        if (null === $this->title) {
            throw new \LogicException('Title must be set to create a map.');
        }

        if (null === $this->zoom) {
            $this->zoom = 15;
        }

        return (new UXMap())
            ->center(new Point($this->latitude, $this->longitude))
            ->zoom($this->zoom)
            ->addMarker(
                new Marker(
                    position: new Point($this->latitude, $this->longitude),
                    title: $this->title,
                ),
            )
            ->options(
                (new LeafletOptions())
                    ->tileLayer(
                        new TileLayer(
                            url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                            options: ['maxZoom' => 19]
                        ),
                    ),
            );
    }
}

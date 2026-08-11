<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class MediaMosaicTest extends KernelTestCase
{
    public function testItRendersOneItemPerMedia(): void
    {
        $html = $this->render(3);

        $this->assertSame(1, substr_count($html, 'class="MediaMosaic"'));
        $this->assertSame(3, substr_count($html, 'class="MediaMosaic__item"'));
        $this->assertSame(3, substr_count($html, 'MediaMosaic__image'));
    }

    /**
     * The mosaic replaced a Splide carousel whose slides were sized independently of the
     * grid, which is what left the huge gaps reported in #100.
     */
    public function testItIsNotACarousel(): void
    {
        $html = $this->render(4);

        $this->assertStringNotContainsString('splide', $html);
        $this->assertStringNotContainsString('data-controller', $html);
    }

    public function testItFallsBackToTheMediaTitleWhenThereIsNoDescription(): void
    {
        $html = $this->render(1);

        $this->assertStringContainsString('title="Média 1"', $html);
        $this->assertStringContainsString('alt="Média 1"', $html);
    }

    public function testItRendersNothingButTheListWhenThereIsNoMedia(): void
    {
        $html = $this->render(0);

        $this->assertStringContainsString('class="MediaMosaic"', $html);
        $this->assertStringNotContainsString('MediaMosaic__item', $html);
    }

    private function render(int $count): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $medias = [];
        for ($i = 1; $i <= $count; ++$i) {
            $medias[] = [
                'title' => 'Média '.$i,
                'url' => '/uploads/media/media-'.$i.'.jpg',
                'thumbnails' => [
                    'x800' => '/uploads/media/x800/media-'.$i.'.jpg',
                    '640x' => '/uploads/media/640x/media-'.$i.'.jpg',
                    '1024x' => '/uploads/media/1024x/media-'.$i.'.jpg',
                ],
                'formats' => [
                    'x800.avif' => '/uploads/media/x800/media-'.$i.'.avif',
                    'x800.webp' => '/uploads/media/x800/media-'.$i.'.webp',
                    '640x.avif' => '/uploads/media/640x/media-'.$i.'.avif',
                    '640x.webp' => '/uploads/media/640x/media-'.$i.'.webp',
                    '1024x.avif' => '/uploads/media/1024x/media-'.$i.'.avif',
                    '1024x.webp' => '/uploads/media/1024x/media-'.$i.'.webp',
                ],
            ];
        }

        return $twig->render('partials/_media_mosaic.html.twig', ['medias' => $medias]);
    }
}

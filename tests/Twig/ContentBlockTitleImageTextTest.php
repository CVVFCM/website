<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class ContentBlockTitleImageTextTest extends KernelTestCase
{
    public function testItRendersTheTextColumnWithoutAnyImage(): void
    {
        $html = $this->render(0);

        $this->assertStringContainsString('ContentBlock--noImage', $html);
        $this->assertStringNotContainsString('ContentBlock__figure', $html);
        $this->assertStringContainsString('<h2 class="ContentBlock__title">Un titre de paragraphe</h2>', $html);
    }

    public function testASingleImageIsNotACarousel(): void
    {
        $html = $this->render(1);

        $this->assertStringNotContainsString('ContentBlock--noImage', $html);
        $this->assertSame(1, substr_count($html, 'ContentBlock__figure'));
        $this->assertStringNotContainsString('data-controller="carousel"', $html);
        $this->assertStringNotContainsString('splide', $html);
    }

    public function testSeveralImagesBecomeACarousel(): void
    {
        $html = $this->render(3);

        $this->assertStringContainsString('data-controller="carousel"', $html);
        $this->assertSame(3, substr_count($html, 'class="splide__slide"'));
        $this->assertSame(3, substr_count($html, 'ContentBlock__figure'));
    }

    public function testTheCaptionComesFromTheMediaDescription(): void
    {
        $html = $this->render(1, 'Une légende de photo');

        $this->assertStringContainsString('<figcaption class="ContentBlock__caption">Une légende de photo</figcaption>', $html);
    }

    public function testAMediaWithoutDescriptionHasNoCaption(): void
    {
        $this->assertStringNotContainsString('ContentBlock__caption', $this->render(1));
    }

    /**
     * Editor lists and links are the block's own content: they must survive into the
     * rich-text container, where the CSS turns a bare list of links into a link menu.
     */
    public function testEditorListsAndLinksAreKept(): void
    {
        $html = $this->render(1);

        $this->assertMatchesRegularExpression('#<div class="ContentBlock__text">.*<ul>.*</ul>.*</div>#s', $html);
        $this->assertStringContainsString('<a href="https://cvvfcm.fr">Kuphal – Howell</a>', $html);
    }

    private function render(int $mediaCount, string $description = ''): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $medias = [];
        for ($i = 1; $i <= $mediaCount; ++$i) {
            $medias[] = [
                'title' => 'Média '.$i,
                'description' => $description,
                'url' => '/uploads/media/media-'.$i.'.jpg',
                'thumbnails' => [
                    'x800' => '/uploads/media/x800/media-'.$i.'.jpg',
                    '640x' => '/uploads/media/640x/media-'.$i.'.jpg',
                    '1024x' => '/uploads/media/1024x/media-'.$i.'.jpg',
                    '1600x' => '/uploads/media/1600x/media-'.$i.'.jpg',
                ],
                'formats' => [
                    'x800.avif' => '/uploads/media/x800/media-'.$i.'.avif',
                    'x800.webp' => '/uploads/media/x800/media-'.$i.'.webp',
                    '640x.avif' => '/uploads/media/640x/media-'.$i.'.avif',
                    '640x.webp' => '/uploads/media/640x/media-'.$i.'.webp',
                    '1024x.avif' => '/uploads/media/1024x/media-'.$i.'.avif',
                    '1024x.webp' => '/uploads/media/1024x/media-'.$i.'.webp',
                    '1600x.avif' => '/uploads/media/1600x/media-'.$i.'.avif',
                    '1600x.webp' => '/uploads/media/1600x/media-'.$i.'.webp',
                ],
            ];
        }

        return $twig->render('partials/_content_block_title_image_text.html.twig', [
            'block' => [
                'title' => 'Un titre de paragraphe',
                'medias' => $medias,
                'text' => '<p>Un chapô.</p><ul><li><a href="https://cvvfcm.fr">Kuphal – Howell</a></li></ul>',
            ],
        ]);
    }
}

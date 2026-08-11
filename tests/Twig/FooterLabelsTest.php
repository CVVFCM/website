<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The footer loads its data through sulu_snippet_load_by_area(), which needs a
 * full Sulu context; the labels markup therefore lives in its own data-less
 * partial that can be rendered in isolation.
 */
final class FooterLabelsTest extends KernelTestCase
{
    /**
     * Issue #101: a label without a link used to be a bare <img>, so it missed
     * the round white gabarit its linked siblings got.
     */
    public function testEveryLabelSharesTheRoundGabarit(): void
    {
        $html = $this->render();

        $this->assertSame(2, substr_count($html, 'SiteFooter__labelLink'));
        $this->assertSame(2, substr_count($html, 'SiteFooter__labelImg'));
        $this->assertStringContainsString('<a class="SiteFooter__labelLink" href="https://www.ffvoile.fr"', $html);
        $this->assertStringContainsString('<span class="SiteFooter__labelLink"', $html);
    }

    public function testOnlyTheLinkedLabelOpensANewTab(): void
    {
        $html = $this->render();

        $this->assertSame(1, substr_count($html, 'target="_blank"'));
        $this->assertSame(1, substr_count($html, 'rel="noopener noreferrer"'));
    }

    public function testTheAltComesFromTheLabelName(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('alt="École Française de Voile"', $html);
        $this->assertStringContainsString('alt="Club handivoile"', $html);
    }

    private function render(): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('partials/_footer_labels.html.twig', ['labels' => [
            [
                'name' => 'École Française de Voile',
                'url' => 'https://www.ffvoile.fr',
                'logo' => ['thumbnails' => ['x200' => '/media/200/efv.png']],
            ],
            [
                'name' => 'Club handivoile',
                'logo' => ['thumbnails' => ['x200' => '/media/200/handivoile.png']],
            ],
        ]]);
    }
}

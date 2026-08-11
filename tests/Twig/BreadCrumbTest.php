<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * partials/_breadcrumb.html.twig needs a real Sulu page tree (sulu_page_breadcrumb
 * walks PHPCR), which a unit test cannot provide. The kernel is still booted the
 * way the other Twig tests do it, but the template is rendered through a bare
 * Environment reusing the container loader, with the three Sulu-side functions
 * stubbed — the container's own Environment is already initialised and refuses
 * further addFunction() calls.
 *
 * Issue #109: the trail is a plain list of links; the '›' separators are CSS
 * ::after pseudo-elements and must never appear in the markup.
 */
final class BreadCrumbTest extends KernelTestCase
{
    public function testItRendersOneItemPerAncestorLevel(): void
    {
        $html = $this->render();

        // Four levels in, minus the current page the template slices off.
        self::assertSame(3, substr_count($html, '<li>'));
        self::assertStringContainsString('Calendrier', $html);
        self::assertStringContainsString('Régates', $html);
        self::assertStringNotContainsString('Régate de printemps', $html);
    }

    public function testTheFirstItemIsTheHomeIconWithItsScreenReaderLabel(): void
    {
        $html = $this->render();

        $firstItem = $this->itemAt($html, 0);

        self::assertStringContainsString('BreadCrumb__homeIcon', $firstItem);
        self::assertStringContainsString('aria-hidden="true"', $firstItem);
        self::assertStringContainsString('<span class="BreadCrumb__srOnly">Accueil</span>', $firstItem);

        // The club's full name is the homepage title: it must not leak in.
        self::assertStringNotContainsString('Cercle de Voile', $html);

        // …and only the home item carries the icon.
        self::assertSame(1, substr_count($html, 'BreadCrumb__homeIcon'));
    }

    /**
     * The separator is drawn by .BreadCrumb li::after, so no item carries one in
     * the markup — least of all the last one, which the stylesheet hides.
     */
    public function testNoItemCarriesASeparatorInTheMarkup(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('›', $html);
        self::assertStringNotContainsString('&rsaquo;', $html);
        self::assertStringEndsWith('Régates</a>', trim($this->itemAt($html, 2)));
    }

    /**
     * Category pages 404 on the website, so they link to their first child page.
     */
    public function testACategoryLevelLinksToItsFirstPage(): void
    {
        $html = $this->render();

        self::assertStringContainsString('<a href="/calendrier/regates/toutes">Régates</a>', $html);
    }

    public function testACategoryWithoutAFirstPageIsNotALink(): void
    {
        $html = $this->render(categoryFirstPageUrl: null);

        self::assertStringContainsString('<span>Régates</span>', $html);
    }

    /**
     * @return string the inner markup of the nth <li>
     */
    private function itemAt(string $html, int $index): string
    {
        $items = explode('<li>', $html);

        self::assertArrayHasKey($index + 1, $items);

        return explode('</li>', $items[$index + 1])[0];
    }

    private function render(?string $categoryFirstPageUrl = '/calendrier/regates/toutes'): string
    {
        self::bootKernel();

        /** @var Environment $containerTwig */
        $containerTwig = self::getContainer()->get('twig');

        $twig = new Environment($containerTwig->getLoader(), [
            'cache' => false,
            'debug' => false,
            'strict_variables' => true,
        ]);

        $twig->addFunction(new TwigFunction(
            'sulu_page_breadcrumb',
            /** @return list<array<string, string>> */
            static fn (string $uuid, array $mapping = []): array => [
                ['title' => 'Cercle de Voile des Vieilles Forges', 'url' => '/', 'template' => 'homepage', 'uuid' => 'uuid-home'],
                ['title' => 'Calendrier', 'url' => '/calendrier', 'template' => 'list', 'uuid' => 'uuid-calendrier'],
                ['title' => 'Régates', 'url' => '/calendrier/regates', 'template' => 'category', 'uuid' => 'uuid-regates'],
                ['title' => 'Régate de printemps', 'url' => '/calendrier/regates/printemps', 'template' => 'event', 'uuid' => 'uuid-current'],
            ],
        ));
        $twig->addFunction(new TwigFunction('sulu_content_path', static fn (string $url): string => $url));
        $twig->addFunction(new TwigFunction('category_first_page_url', static fn (?string $uuid = null): ?string => $categoryFirstPageUrl));

        return $twig->render('partials/_breadcrumb.html.twig', ['uuid' => 'uuid-current']);
    }
}

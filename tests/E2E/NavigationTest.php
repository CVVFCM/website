<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NavigationTest extends WebTestCase
{
    public function testHeaderBarStructure(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        // The burger is a real button wired to the menu panel.
        $toggle = $crawler->filter('button.MainNavigation__toggle');
        $this->assertCount(1, $toggle);
        $this->assertSame('main-navigation-menu', $toggle->attr('aria-controls'));
        $this->assertSame('false', $toggle->attr('aria-expanded'));

        // Color logo with the HTML wordmark next to it.
        $logo = $crawler->filter('.MainNavigation__logo img');
        $this->assertStringContainsString('logo_cvvfcm_color', (string) $logo->attr('src'));
        $this->assertStringContainsString('Club de Voile', $crawler->filter('.MainNavigation__wordmark')->html());
    }

    public function testQuickLinksAreRenderedTwice(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Bar variant in the closed header, grid variant inside the panel.
        $this->assertCount(1, $crawler->filter('.MainNavigation__bar .NavigationLinks__list--bar'));
        $grid = $crawler->filter('.MainNavigation__container .NavigationLinks__list--grid');
        $this->assertCount(1, $grid);
        $this->assertGreaterThanOrEqual(4, $grid->filter('.NavigationLinks__item')->count());
    }

    public function testCategorySectionsHaveAccessibleToggles(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $toggles = $crawler->filter('.MainNavigation__list > li > .MainNavigation__sectionToggle');
        $this->assertGreaterThan(0, $toggles->count());

        // Every toggle controls the sub-list it sits next to.
        $toggles->each(function ($toggle) use ($crawler): void {
            $controls = $toggle->attr('aria-controls');
            $this->assertNotEmpty($controls);
            $this->assertSame('false', $toggle->attr('aria-expanded'));
            $this->assertCount(1, $crawler->filter(sprintf('ul[id="%s"]', $controls)));
        });

        // Category links keep navigating (structure relied on by CategoryPageTest).
        $this->assertGreaterThan(
            0,
            $crawler->filter('.MainNavigation__item--category > a.MainNavigation__link')->count(),
        );
    }
}

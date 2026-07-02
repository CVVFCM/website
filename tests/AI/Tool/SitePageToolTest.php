<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\SitePageTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SitePageToolTest extends KernelTestCase
{
    public function testItListsPublishedNonEventPages(): void
    {
        $result = $this->tool()->list();

        $this->assertNotEmpty($result['pages']);

        foreach ($result['pages'] as $page) {
            $this->assertSame(['titre', 'url', 'description'], array_keys($page));
            $this->assertNotSame('', $page['titre']);
            if (null !== $page['description']) {
                $this->assertLessThanOrEqual(201, mb_strlen($page['description']));
            }
        }

        // Event pages must not leak into the site map.
        $urls = array_column($result['pages'], 'url');
        foreach ($urls as $url) {
            $this->assertNotNull($url);
        }
    }

    public function testItFetchesFullPageContentByUrl(): void
    {
        $pages = $this->tool()->list()['pages'];
        $url = $pages[array_key_last($pages)]['url'];

        $result = $this->tool()->content($url);

        $this->assertArrayNotHasKey('erreur', $result);
        $this->assertSame($url, $result['url']);
        $this->assertNotSame('', $result['titre']);
        $this->assertNotSame('', $result['contenu']);
    }

    public function testUnknownUrlYieldsError(): void
    {
        $this->assertSame(
            ['erreur' => 'Page introuvable.'],
            $this->tool()->content('/nope/nothing-here'),
        );
    }

    private function tool(): SitePageTool
    {
        self::bootKernel();

        return static::getContainer()->get(SitePageTool::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;

final class SearchPageTest extends WebTestCase
{
    /**
     * The Loupe index lives on disk (var/indexes) and is shared by the whole test run:
     * build it once, then let every test query it.
     */
    private static bool $indexed = false;

    public function testItShowsTheSearchFormWhenNoQueryIsGiven(): void
    {
        $client = static::createClient();
        $client->request('GET', '/recherche');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSelectorExists('form[role="search"] input[name="q"]');
        self::assertSelectorNotExists('.SearchPage__list');
    }

    public function testTheResultsPageIsNotIndexable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/recherche');

        self::assertStringContainsString('noIndex', (string) $client->getResponse()->getContent());
    }

    public function testItFindsAPageOnAWordFromItsBody(): void
    {
        $client = static::createClient();
        $this->reindex($client);

        // "doublée" only appears in the body of the regattas page, never in a page title:
        // finding it proves the content itself is indexed, not just the titles.
        $client->request('GET', '/recherche?q=doublée');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSelectorExists('.SearchPage__result');
        self::assertSelectorTextContains('.SearchPage__resultSnippet', 'doublée');
    }

    public function testItHighlightsTheMatchedTerms(): void
    {
        $client = static::createClient();
        $this->reindex($client);

        $client->request('GET', '/recherche?q=régate');

        // The engine only formats the title field (Loupe does not highlight the multi-valued
        // `content` field), so the <mark> tags are expected on result titles.
        self::assertSelectorExists('.SearchPage__resultTitle mark');
    }

    public function testItShowsAnEmptyStateWhenNothingMatches(): void
    {
        $client = static::createClient();
        $this->reindex($client);

        $client->request('GET', '/recherche?q=xyzzyplughquux');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSelectorNotExists('.SearchPage__result');
        self::assertSelectorTextContains('.SearchPage__note', 'Aucune page');
    }

    private function reindex(KernelBrowser $client): void
    {
        if (self::$indexed) {
            return;
        }

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);
        $application->run(
            new ArrayInput(['command' => 'cmsig:seal:reindex', '--drop' => true, '--no-interaction' => true]),
            new NullOutput(),
        );

        self::$indexed = true;
    }
}

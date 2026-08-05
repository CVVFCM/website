<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ErrorPageTest extends WebTestCase
{
    public function testTheNotFoundPageIsServedWithAnHtmlTitle(): void
    {
        // Sulu's ErrorController short-circuits to the Symfony exception page when debug is on,
        // so the webspace error template only renders with debug off.
        $client = static::createClient(['debug' => false]);

        $client->request('GET', '/une-page-qui-nexiste-pas');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertStringContainsString(
            '<title>Page introuvable — CVVFCM</title>',
            (string) $client->getResponse()->getContent(),
        );
    }
}

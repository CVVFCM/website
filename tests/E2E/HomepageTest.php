<?php

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomepageTest extends WebTestCase
{
    public function testItResponds(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    #[Depends('testItResponds')]
    public function testRegattaLinkIsClickable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $client->clickLink('Régates');

        $this->assertResponseIsSuccessful();
    }

    #[Depends('testItResponds')]
    public function testLiveLinkIsClickable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $client->clickLink('Webcam');

        $this->assertResponseIsSuccessful();
    }
}

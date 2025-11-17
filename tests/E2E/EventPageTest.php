<?php
declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventPageTest extends WebTestCase
{
    public function testItResponds(): void
    {
        $client = static::createClient();
        $client->request('GET', '/evenements/regates/trophee-du-coeur-de-l-europe/trophee-du-coeur-de-l-europe-' . date('Y'));

        $this->assertResponseIsSuccessful();
    }
}

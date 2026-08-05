<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HealthzController;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthzControllerTest extends WebTestCase
{
    public function testRepliesNoContentWhenTheDatabaseAnswers(): void
    {
        $client = static::createClient();
        $client->request('GET', '/healthz');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', (string) $client->getResponse()->getContent());
        $this->assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testRepliesServiceUnavailableWhenTheDatabaseIsDown(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(
            new \RuntimeException('SQLSTATE[08006] could not connect to server'),
        );

        $response = new HealthzController($connection)();

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }
}

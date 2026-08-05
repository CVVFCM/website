<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health endpoint for the container HEALTHCHECK and the Kubernetes probes:
 * 204 when the PHP worker serves requests and the database answers a
 * "SELECT 1", 503 otherwise. Deliberately schema-free so a fresh container
 * is healthy as soon as the database accepts connections, before any
 * migration or fixture ran. The empty 204 body also keeps Sulu's
 * AppendAnalyticsListener away (it only rewrites text/html responses).
 */
final readonly class HealthzController
{
    public function __construct(private Connection $connection)
    {
    }

    #[Route('/healthz', name: 'healthz', methods: ['GET'])]
    public function __invoke(): Response
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return new Response(null, Response::HTTP_SERVICE_UNAVAILABLE, ['Cache-Control' => 'no-store']);
        }

        return new Response(null, Response::HTTP_NO_CONTENT, ['Cache-Control' => 'no-store']);
    }
}

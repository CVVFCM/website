<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness endpoint for the container HEALTHCHECK. Deliberately DB-free so a
 * fresh container is healthy before the database is provisioned, while still
 * going through the PHP worker — a poisoned worker thread fails to serve it.
 */
final class HealthzController
{
    #[Route('/healthz', name: 'healthz', methods: ['GET'])]
    public function __invoke(): Response
    {
        // text/plain is load-bearing: Sulu's AppendAnalyticsListener queries the
        // we_analytics table for every text/html response, which breaks the DB-free
        // guarantee on a container that starts before the schema exists.
        return new Response('ok', Response::HTTP_OK, [
            'Cache-Control' => 'no-store',
            'Content-Type' => 'text/plain',
        ]);
    }
}

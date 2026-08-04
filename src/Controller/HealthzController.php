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
        return new Response('ok', Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}

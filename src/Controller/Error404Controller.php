<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class Error404Controller
{
    public function __invoke(): void
    {
        throw new NotFoundHttpException();
    }
}

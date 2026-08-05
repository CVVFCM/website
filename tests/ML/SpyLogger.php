<?php

declare(strict_types=1);

namespace App\Tests\ML;

use Psr\Log\AbstractLogger;

/**
 * Records every log call so tests can assert on level/message/context — psr/log 3 no longer ships
 * a TestLogger.
 */
final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}

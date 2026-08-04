<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent;

/**
 * Structured output of the judge agent (response_format).
 */
final class Verdict
{
    /** true only when the evaluated criterion is fully met. */
    public bool $pass = false;

    /** Short justification, used in the failure message. */
    public string $reason = '';
}

<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Ask Forgie (the AI assistant) to answer a user message. Handled asynchronously;
 * the answer is streamed to the client over Mercure on the conversation topic.
 */
final readonly class AskForgie
{
    public function __construct(
        public string $conversationId,
        public string $message,
        public ?string $uploadId = null,
    ) {
    }
}

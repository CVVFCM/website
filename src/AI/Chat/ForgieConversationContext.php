<?php

declare(strict_types=1);

namespace App\AI\Chat;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Holds the MessageBag of the conversation currently being streamed, so a tool
 * (SendContactMessageTool) can read the exact verbatim — including the current
 * user turn, which is only written to the database once the stream completes.
 *
 * Safe as a shared mutable singleton: the Messenger worker processes one AskForgie
 * message at a time, and ForgieChat::stream() sets the bag before any tool runs.
 */
final class ForgieConversationContext
{
    private ?MessageBag $messages = null;

    public function set(MessageBag $messages): void
    {
        $this->messages = $messages;
    }

    public function get(): ?MessageBag
    {
        return $this->messages;
    }
}

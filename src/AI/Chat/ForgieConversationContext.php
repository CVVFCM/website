<?php

declare(strict_types=1);

namespace App\AI\Chat;

use App\Entity\ForgieUpload;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Holds the MessageBag of the conversation currently being streamed, so a tool
 * (SendContactMessageTool) can read the exact verbatim — including the current
 * user turn, which is only written to the database once the stream completes.
 * Also carries the image the visitor attached to the current turn (if any), so the
 * contact tool can forward it as an email attachment.
 *
 * Safe as a shared mutable singleton: the Messenger worker processes one AskForgie
 * message at a time, and ForgieChat::stream() sets the bag before any tool runs.
 */
final class ForgieConversationContext
{
    private ?MessageBag $messages = null;

    private ?ForgieUpload $upload = null;

    public function set(MessageBag $messages): void
    {
        $this->messages = $messages;
    }

    public function get(): ?MessageBag
    {
        return $this->messages;
    }

    public function setUpload(?ForgieUpload $upload): void
    {
        $this->upload = $upload;
    }

    public function getUpload(): ?ForgieUpload
    {
        return $this->upload;
    }
}

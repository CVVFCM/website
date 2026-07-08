<?php

declare(strict_types=1);

namespace App\AI\Chat;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\AgentInterface;

/**
 * One chat per conversation: the store binds to a single conversation, so the
 * store (and thus the chat) is instantiated per conversationId.
 */
final readonly class ForgieChatFactory
{
    public function __construct(
        private AgentInterface $agent,
        private EntityManagerInterface $entityManager,
        private ForgieConversationContext $context,
    ) {
    }

    public function create(string $conversationId): ForgieChat
    {
        return new ForgieChat($this->agent, new ForgieMessageStore($this->entityManager, $conversationId), $this->context);
    }
}

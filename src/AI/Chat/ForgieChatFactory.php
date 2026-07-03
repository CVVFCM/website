<?php

declare(strict_types=1);

namespace App\AI\Chat;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\ChatInterface;

/**
 * One Chat per conversation: the ai-chat component binds a store to a single
 * conversation, so the store (and thus the Chat) is instantiated per conversationId.
 */
final readonly class ForgieChatFactory
{
    public function __construct(
        private AgentInterface $agent,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(string $conversationId): ChatInterface
    {
        return new Chat($this->agent, new ForgieMessageStore($this->entityManager, $conversationId));
    }
}

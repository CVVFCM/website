<?php

declare(strict_types=1);

namespace App\AI\Chat;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * One chat per conversation: the store binds to a single conversation, so the
 * store (and thus the chat) is instantiated per conversationId.
 */
final readonly class ForgieChatFactory
{
    public function __construct(
        // Target is load-bearing: the bare AgentInterface alias disappears as soon
        // as a second agent exists (the test-only judge in ai.yaml).
        #[Target('forgie')]
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

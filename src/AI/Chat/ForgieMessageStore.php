<?php

declare(strict_types=1);

namespace App\AI\Chat;

use App\Entity\ForgieConversation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Contract\Normalizer\Result\ToolCallNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

use function Symfony\Component\Clock\now;

/**
 * ai-chat message store scoped to one conversation: a single forgie_conversation row
 * holds the whole serialized bag (same serializer stack as the component's own
 * DoctrineDbalMessageStore, which is one-table-per-conversation and thus unusable here).
 * load() caps the history sent to the model to the most recent messages.
 */
final readonly class ForgieMessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    private const int MAX_MESSAGES = 20;

    private Serializer $serializer;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $conversationId,
    ) {
        $this->serializer = new Serializer(
            [new ArrayDenormalizer(), new ToolCallNormalizer(), new MessageNormalizer()],
            [new JsonEncoder()],
        );
    }

    #[\Override]
    public function setup(array $options = []): void
    {
        // Schema is managed by Doctrine migrations.
    }

    #[\Override]
    public function save(MessageBag $messages): void
    {
        /** @var array<array-key, mixed> $payload */
        $payload = $this->serializer->normalize($messages->getMessages());

        $conversation = $this->find();
        if (null === $conversation) {
            $conversation = new ForgieConversation($this->conversationId, $payload, now());
            $this->entityManager->persist($conversation);
        } else {
            $conversation->messages = $payload;
            $conversation->updatedAt = now();
        }

        $this->entityManager->flush();
    }

    #[\Override]
    public function load(): MessageBag
    {
        $conversation = $this->find();
        if (null === $conversation) {
            return new MessageBag();
        }

        /** @var list<MessageInterface> $messages */
        $messages = $this->serializer->denormalize($conversation->messages, MessageInterface::class.'[]');

        return new MessageBag(...\array_slice($messages, -self::MAX_MESSAGES));
    }

    #[\Override]
    public function drop(): void
    {
        $conversation = $this->find();
        if (null !== $conversation) {
            $this->entityManager->remove($conversation);
            $this->entityManager->flush();
        }
    }

    private function find(): ?ForgieConversation
    {
        return $this->entityManager->find(ForgieConversation::class, $this->conversationId);
    }
}

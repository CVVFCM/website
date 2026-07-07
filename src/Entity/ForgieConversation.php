<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ForgieConversationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;

/**
 * @api
 */
#[Entity(repositoryClass: ForgieConversationRepository::class)]
#[Table(name: 'forgie_conversation')]
#[Index(name: 'idx_forgie_conversation_updated_at', columns: ['updated_at'])]
class ForgieConversation
{
    public function __construct(
        #[Id]
        #[Column(type: Types::GUID)]
        public readonly string $conversationId,
        #[Column(type: Types::JSON)]
        public array $messages,
        #[Column(type: Types::DATETIME_IMMUTABLE)]
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}

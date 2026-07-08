<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ForgieUploadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;

/**
 * An image a visitor attached to a Forgie message. Stored (base64) only long
 * enough to be shown to the model on its turn and, if the visitor asks to be
 * contacted, forwarded to the admins as an email attachment. Purged on schedule
 * (PurgeForgieUploads); never kept in the conversation history.
 *
 * @api
 */
#[Entity(repositoryClass: ForgieUploadRepository::class)]
#[Table(name: 'forgie_upload')]
#[Index(name: 'idx_forgie_upload_created_at', columns: ['created_at'])]
class ForgieUpload
{
    public function __construct(
        #[Id]
        #[Column(type: Types::GUID)]
        public readonly string $id,
        #[Column(type: Types::GUID)]
        public readonly string $conversationId,
        // Image bytes, base64-encoded (Postgres has no portable inline binary literal
        // and base64 keeps the value a plain string end to end).
        #[Column(type: Types::TEXT)]
        public readonly string $data,
        #[Column(type: Types::STRING, length: 100)]
        public readonly string $mimeType,
        #[Column(type: Types::STRING, length: 255)]
        public readonly string $filename,
        #[Column(type: Types::INTEGER)]
        public readonly int $size,
        #[Column(type: Types::DATETIME_IMMUTABLE)]
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}

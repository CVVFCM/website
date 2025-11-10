<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class FacebookPost
{
    /**
     * @param string[] $messageTagNames
     */
    public function __construct(
        public string $id,
        public string $permalinkUrl,
        public ?string $picture,
        public string $message,
        public \DateTimeImmutable $createdAt,
        public array $messageTagNames,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class InstagramMedia
{
    public function __construct(
        public string $id,
        public string $type,
        public string $caption,
        public string $permalink,
        public string $mediaUrl,
    ) {
    }
}

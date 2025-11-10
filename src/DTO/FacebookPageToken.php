<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class FacebookPageToken
{
    public function __construct(
        public string $pageName,
        public string $accessToken,
        public string $instagramId,
    ) {
    }
}

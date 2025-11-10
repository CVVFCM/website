<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FacebookTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * @api
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[Entity(repositoryClass: FacebookTokenRepository::class)]
#[Table]
class FacebookToken
{
    public const string RESOURCE_KEY = 'facebook_tokens';

    #[Id]
    #[GeneratedValue(strategy: 'AUTO')]
    #[Column(type: Types::INTEGER)]
    public readonly int $id;

    #[Column(type: Types::STRING, length: 2048)]
    public readonly string $token;

    #[Column(type: Types::STRING, length: 2048)]
    public readonly string $pageToken;

    #[Column(type: Types::STRING, length: 255)]
    public readonly string $pageName;

    #[Column(type: Types::STRING, length: 255)]
    public readonly string $instagramId;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $expiresAt;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $createdAt;

    public function __construct(
        string $token,
        \DateTimeImmutable $expiresAt,
        string $pageToken,
        string $pageName,
        string $instagramId,
    ) {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->pageToken = $pageToken;
        $this->pageName = $pageName;
        $this->instagramId = $instagramId;
    }
}

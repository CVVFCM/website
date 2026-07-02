<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\ForgieMessageProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A user message sent to Forgie. Accepted (202) and processed asynchronously;
 * the answer is streamed over Mercure on /forgie/conversations/{conversationId}.
 */
#[ApiResource(
    shortName: 'ForgieMessage',
    operations: [
        new Post(
            uriTemplate: '/forgie/messages',
            status: 202,
            output: false,
            processor: ForgieMessageProcessor::class,
        ),
    ],
)]
final class ForgieMessage
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $conversationId = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 4000)]
    public string $message = '';
}

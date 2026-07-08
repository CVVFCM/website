<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\ForgieMessageProcessor;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    #[Assert\Length(max: 4000)]
    public string $message = '';

    /**
     * Id of a previously uploaded image (POST /api/forgie/uploads) to attach to this message.
     */
    #[Assert\Uuid]
    public ?string $uploadId = null;

    #[Assert\Callback]
    public function validateContent(ExecutionContextInterface $context): void
    {
        // A message must carry text or an image (or both) — never be empty.
        if ('' === trim($this->message) && null === $this->uploadId) {
            $context->buildViolation('This value should not be blank.')
                ->atPath('message')
                ->addViolation();
        }
    }
}

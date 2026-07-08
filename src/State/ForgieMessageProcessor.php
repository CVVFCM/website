<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ForgieMessage;
use App\Message\AskForgie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Accepts a Forgie message: rate-limits per client IP, then dispatches the
 * question to the async bus. The HTTP response is an empty 202.
 *
 * @implements ProcessorInterface<ForgieMessage, null>
 */
final readonly class ForgieMessageProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RateLimiterFactory $forgieApiLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param ForgieMessage $data
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $clientIp = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $limit = $this->forgieApiLimiter->create($clientIp)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $this->messageBus->dispatch(new AskForgie($data->conversationId, $data->message, $data->uploadId));

        return null;
    }
}

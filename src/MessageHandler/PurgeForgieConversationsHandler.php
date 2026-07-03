<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeForgieConversations;
use App\Repository\ForgieConversationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function Symfony\Component\Clock\now;

#[AsMessageHandler]
final readonly class PurgeForgieConversationsHandler
{
    private const string RETENTION = '-24 hours';

    public function __construct(
        private ForgieConversationRepository $repository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @psalm-suppress UnusedParam The message is an empty marker, only routing matters.
     */
    public function __invoke(PurgeForgieConversations $message): void
    {
        $purged = $this->repository->purgeOlderThan(now()->modify(self::RETENTION));

        if ($purged > 0) {
            $this->logger->info('Purged stale Forgie conversations', ['count' => $purged]);
        }
    }
}

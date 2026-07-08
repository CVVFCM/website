<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeForgieUploads;
use App\Repository\ForgieUploadRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function Symfony\Component\Clock\now;

#[AsMessageHandler]
final readonly class PurgeForgieUploadsHandler
{
    private const string RETENTION = '-6 hours';

    public function __construct(
        private ForgieUploadRepository $repository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @psalm-suppress UnusedParam The message is an empty marker, only routing matters.
     */
    public function __invoke(PurgeForgieUploads $message): void
    {
        $purged = $this->repository->purgeOlderThan(now()->modify(self::RETENTION));

        if ($purged > 0) {
            $this->logger->info('Purged stale Forgie uploads', ['count' => $purged]);
        }
    }
}

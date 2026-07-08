<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForgieUpload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForgieUpload>
 */
final class ForgieUploadRepository extends ServiceEntityRepository
{
    /**
     * @psalm-suppress UnusedParam
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForgieUpload::class);
    }

    /**
     * @psalm-suppress UnusedParam Same false positive as in ForgieConversationRepository.
     */
    public function purgeOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('u')
            ->delete()
            ->where('u.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}

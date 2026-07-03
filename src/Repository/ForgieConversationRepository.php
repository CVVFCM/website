<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForgieConversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForgieConversation>
 */
final class ForgieConversationRepository extends ServiceEntityRepository
{
    /**
     * @psalm-suppress UnusedParam
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForgieConversation::class);
    }

    /**
     * @psalm-suppress UnusedParam Same false positive as in FacebookTokenRepository.
     */
    public function purgeOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.updatedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}

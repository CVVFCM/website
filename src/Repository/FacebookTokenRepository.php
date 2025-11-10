<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FacebookToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class FacebookTokenRepository extends ServiceEntityRepository
{
    /**
     * @psalm-suppress UnusedParam
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FacebookToken::class);
    }

    public function getLast(): FacebookToken
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @psalm-suppress UnusedParam
     */
    public function save(FacebookToken $facebookToken): void
    {
        $this->getEntityManager()->persist($facebookToken);
        $this->getEntityManager()->flush();
    }

    /**
     * @psalm-suppress UnusedParam
     */
    public function remove(FacebookToken $facebookToken): void
    {
        $this->getEntityManager()->remove($facebookToken);
        $this->getEntityManager()->flush();
    }
}

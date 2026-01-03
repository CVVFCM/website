<?php

namespace App\Repository;

use App\Entity\WeatherForecastRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WeatherForecastRecordRepository extends ServiceEntityRepository
{
    private const int BATCH_SIZE = 1000;
    private int $currentBatchSize = 0;

    /**
     * @psalm-suppress UnusedParam Don't know why it complains about this
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherForecastRecord::class);
    }

    /**
     * @psalm-suppress UnusedParam Don't know why it complains about this
     */
    public function save(WeatherForecastRecord $forecastRecord): void
    {
        $this->getEntityManager()->persist($forecastRecord);
        $this->getEntityManager()->flush();
    }

    /**
     * @psalm-suppress UnusedParam Don't know why it complains about this
     */
    public function saveDeferred(WeatherForecastRecord $forecastRecord): void
    {
        $this->getEntityManager()->persist($forecastRecord);

        if (++$this->currentBatchSize >= self::BATCH_SIZE) {
            $this->saveBatch();
        }
    }

    public function saveBatch(): void
    {
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->currentBatchSize = 0;
    }
}

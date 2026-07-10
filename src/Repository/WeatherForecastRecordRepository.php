<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WeatherForecastRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class WeatherForecastRecordRepository extends ServiceEntityRepository
{
    private const int BATCH_SIZE = 1000;
    // Forecasts are hourly; anything further than this from an observation is not a meaningful match.
    private const int MAX_MATCH_SECONDS = 3 * 3600;

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

    /**
     * The most recent forecast fetch time (newest createdAt), or null when the table is empty. Used to
     * decide whether the forecast is stale enough to refetch.
     */
    public function findLatestCreatedAt(): ?\DateTimeImmutable
    {
        /** @var ?WeatherForecastRecord $latest */
        $latest = $this->createQueryBuilder('w')
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $latest?->createdAt;
    }

    /**
     * The forecast record closest in time to $when, or null if the nearest is further than
     * MAX_MATCH_SECONDS (no meaningful forecast for that moment).
     *
     * @psalm-suppress UnusedParam Same false positive as the other methods here.
     */
    public function findNearest(\DateTimeImmutable $when): ?WeatherForecastRecord
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata(WeatherForecastRecord::class, 'w');

        /** @var ?WeatherForecastRecord $record */
        $record = $this->getEntityManager()
            ->createNativeQuery(
                'SELECT '.$rsm->generateSelectClause().' FROM weather_forecast_record w '
                .'ORDER BY ABS(EXTRACT(EPOCH FROM (w.date - :when))) ASC LIMIT 1',
                $rsm,
            )
            ->setParameter('when', $when->format('Y-m-d H:i:s'))
            ->getOneOrNullResult();

        if (null === $record || abs($record->date->getTimestamp() - $when->getTimestamp()) > self::MAX_MATCH_SECONDS) {
            return null;
        }

        return $record;
    }
}

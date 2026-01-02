<?php

namespace App\Repository;

use App\Entity\LiveWeatherRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class LiveWeatherRecordRepository extends ServiceEntityRepository
{
    private const int BATCH_SIZE = 1000;
    private int $currentBatchSize = 0;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveWeatherRecord::class);
    }

    public function save(LiveWeatherRecord $liveWeatherRecord): void
    {
        $this->getEntityManager()->persist($liveWeatherRecord);
        $this->getEntityManager()->flush();
    }

    public function saveDeferred(LiveWeatherRecord $liveWeatherRecord): void
    {
        $this->getEntityManager()->persist($liveWeatherRecord);

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
     * @return list<array{
     *     recorded_hour: string,
     *     humidity: float,
     *     pressure: float,
     *     temperature: float,
     *     wind_sin: float,
     *     wind_cos: float,
     *     hour_sin: float,
     *     hour_cos: float,
     *     day_sin: float,
     *     day_cos: float,
     *     pressure_minus_3h: float|null,
     *     pressure_trend_3h: float|null,
     * }>
     */
    public function findAllWithIterator(): iterable
    {
        $rsm = $this->createResultSetMappingBuilder(LiveWeatherRecord::class)
            ->addScalarResult('recorded_hour', 'recorded_hour')
            ->addScalarResult('humidity', 'humidity')
            ->addScalarResult('pressure', 'pressure')
            ->addScalarResult('temperature', 'temperature')
            ->addScalarResult('wind_sin', 'wind_sin')
            ->addScalarResult('wind_cos', 'wind_cos')
            ->addScalarResult('hour_sin', 'hour_sin')
            ->addScalarResult('hour_cos', 'hour_cos')
            ->addScalarResult('day_sin', 'day_sin')
            ->addScalarResult('day_cos', 'day_cos')
            ->addScalarResult('pressure_minus_3h', 'pressure_minus_3h')
            ->addScalarResult('pressure_trend_3h', 'pressure_trend_3h');

        return $this->getEntityManager()->createNativeQuery(
            <<<SQL
                WITH hourly_metrics AS (
                    SELECT
                        DATE_TRUNC('hour', lwr.recorded_at) AS recorded_hour,
                        AVG(lwr.humidity) AS humidity,
                        AVG(lwr.pressure) AS pressure,
                        AVG(lwr.temperature) AS temperature,
                        AVG(SIN(RADIANS(lwr.wind_direction)) * lwr.wind_speed) AS wind_sin,
                        AVG(COS(RADIANS(lwr.wind_direction)) * lwr.wind_speed) AS wind_cos
                    FROM live_weather_record lwr
                    GROUP BY recorded_hour
                    HAVING COUNT(*) >= 6
                )
                SELECT
                    hourly_metrics.*,
                    SIN(2 * PI() * (EXTRACT(HOURS FROM hourly_metrics.recorded_hour) / 24)) AS hour_sin,
                    COS(2 * PI() * (EXTRACT(HOURS FROM hourly_metrics.recorded_hour) / 24)) AS hour_cos,
                    SIN(2 * PI() * (EXTRACT(DOY FROM hourly_metrics.recorded_hour) / 365.25)) AS day_sin,
                    COS(2 * PI() * (EXTRACT(DOY FROM hourly_metrics.recorded_hour) / 365.25)) AS day_cos,
                    t_minus_3.pressure AS pressure_minus_3h,
                    hourly_metrics.pressure - t_minus_3.pressure AS pressure_trend_3h
                FROM hourly_metrics
                LEFT JOIN hourly_metrics t_minus_3 ON t_minus_3.recorded_hour = hourly_metrics.recorded_hour - INTERVAL '3 hours'
                ORDER BY hourly_metrics.recorded_hour;
            SQL,
            $rsm,
        )->toIterable();
    }
}

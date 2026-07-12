<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveWeatherRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class LiveWeatherRecordRepository extends ServiceEntityRepository
{
    private const int BATCH_SIZE = 1000;
    private int $currentBatchSize = 0;

    /**
     * @psalm-suppress UnusedParam Don't know why it complains about this
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveWeatherRecord::class);
    }

    /**
     * @psalm-suppress UnusedParam Don't know why it complains about this
     */
    public function save(LiveWeatherRecord $liveWeatherRecord): void
    {
        $this->getEntityManager()->persist($liveWeatherRecord);
        $this->getEntityManager()->flush();
    }

    /**
     * @psalm-suppress UnusedParam Don't know why it complains about this
     */
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
     * The most recent observation, or null when the table is empty. Read on the homepage instead of
     * calling the live station on every render (the app:import:live-weather cron keeps it fresh).
     */
    public function findLatest(): ?LiveWeatherRecord
    {
        /** @var ?LiveWeatherRecord $record */
        $record = $this->createQueryBuilder('l')
            ->orderBy('l.recordedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $record;
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
            ->addScalarResult('pressure_trend_3h', 'pressure_trend_3h')
            ->addScalarResult('forecast_temperature', 'forecast_temperature')
            ->addScalarResult('forecast_humidity', 'forecast_humidity')
            ->addScalarResult('forecast_pressure', 'forecast_pressure')
            ->addScalarResult('forecast_wind_sin', 'forecast_wind_sin')
            ->addScalarResult('forecast_wind_cos', 'forecast_wind_cos')
        ;

        return $this->getEntityManager()->createNativeQuery(
            <<<SQL
                WITH hourly_live_record AS (
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
                    hourly_live_record.*,
                    SIN(2 * PI() * (EXTRACT(HOURS FROM hourly_live_record.recorded_hour) / 24)) AS hour_sin,
                    COS(2 * PI() * (EXTRACT(HOURS FROM hourly_live_record.recorded_hour) / 24)) AS hour_cos,
                    SIN(2 * PI() * (EXTRACT(DOY FROM hourly_live_record.recorded_hour) / 365.25)) AS day_sin,
                    COS(2 * PI() * (EXTRACT(DOY FROM hourly_live_record.recorded_hour) / 365.25)) AS day_cos,
                    t_minus_3.pressure AS pressure_minus_3h,
                    hourly_live_record.pressure - t_minus_3.pressure AS pressure_trend_3h,
                    weather_forecast_record.temperature AS forecast_temperature,
                    weather_forecast_record.humidity AS forecast_humidity,
                    weather_forecast_record.pressure AS forecast_pressure,
                    SIN(RADIANS(weather_forecast_record.wind_direction)) * weather_forecast_record.wind_speed AS forecast_wind_sin,
                    COS(RADIANS(weather_forecast_record.wind_direction)) * weather_forecast_record.wind_speed AS forecast_wind_cos
                FROM hourly_live_record
                INNER JOIN weather_forecast_record ON hourly_live_record.recorded_hour = weather_forecast_record.date
                LEFT JOIN hourly_live_record t_minus_3 ON t_minus_3.recorded_hour = hourly_live_record.recorded_hour - INTERVAL '3 hours'
                ORDER BY hourly_live_record.recorded_hour;
            SQL,
            $rsm,
        )->toIterable();
    }
}

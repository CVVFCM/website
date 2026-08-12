<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\LiveWeatherRecord;
use App\Entity\WeatherForecastRecord;
use App\Repository\LiveWeatherRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LiveWeatherRecordExportTest extends KernelTestCase
{
    private const string HOUR = '2024-05-05 10:00:00';

    public function testItKeepsOnlyTheFreshestForecastFetchOfEachHour(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var LiveWeatherRecordRepository $repository */
        $repository = static::getContainer()->get(LiveWeatherRecordRepository::class);

        $this->persistAnHourOfObservations($em);

        // Two fetches of the same forecast hour, as the hourly refresh produces in production.
        $stale = WeatherForecastRecord::fromArray([
            'date' => new \DateTimeImmutable(self::HOUR),
            'humidity' => '99',
            'pressure' => '999',
            'temperature' => '9',
            'windDirection' => '270',
            'windSpeed' => '5',
        ]);
        $fresh = WeatherForecastRecord::fromArray([
            'date' => new \DateTimeImmutable(self::HOUR),
            'humidity' => '55',
            'pressure' => '1005',
            'temperature' => '21',
            'windDirection' => '90',
            'windSpeed' => '20',
        ]);
        $em->persist($stale);
        $em->persist($fresh);
        $em->flush();

        // createdAt is stamped in the constructor and stored as TIMESTAMP(0): both rows land on the
        // same second here, which would leave the tie unbroken. Space them out explicitly so the
        // query has a real ordering to work with.
        $connection = $em->getConnection();
        $connection->executeStatement(
            'UPDATE weather_forecast_record SET created_at = :createdAt WHERE id = :id',
            ['createdAt' => '2024-05-04 00:00:00', 'id' => $stale->id->toRfc4122()],
        );
        $connection->executeStatement(
            'UPDATE weather_forecast_record SET created_at = :createdAt WHERE id = :id',
            ['createdAt' => '2024-05-05 09:00:00', 'id' => $fresh->id->toRfc4122()],
        );

        $rows = [];
        foreach ($repository->findAllWithIterator() as $row) {
            if (self::HOUR === $row['recorded_hour']) {
                $rows[] = $row;
            }
        }

        $this->assertCount(1, $rows, 'One observed hour must yield one training sample, whatever the number of forecast fetches.');
        $this->assertSame(55.0, (float) $rows[0]['forecast_humidity']);
        $this->assertSame(1005.0, (float) $rows[0]['forecast_pressure']);
        $this->assertSame(21.0, (float) $rows[0]['forecast_temperature']);
        // Wind blowing from 90° at 20 kn: the sine carries the whole vector, the cosine is zero.
        $this->assertEqualsWithDelta(20.0, (float) $rows[0]['forecast_wind_sin'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $rows[0]['forecast_wind_cos'], 0.001);
    }

    /**
     * A wind-only historical hour is a single row, so the six-samples rule would normally discard
     * it. The hourly_mean flag is what lets it through, and the wind vector must survive the trip
     * through the speed/bearing columns the table stores.
     */
    public function testAnHourlyMeanIsExportedOnItsOwn(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var LiveWeatherRecordRepository $repository */
        $repository = static::getContainer()->get(LiveWeatherRecordRepository::class);

        $hour = '2024-06-06 14:00:00';
        // 9 kn from 240°, projected on the two axes.
        $windSin = sin(deg2rad(240.0)) * 9.0;
        $windCos = cos(deg2rad(240.0)) * 9.0;

        $em->persist(LiveWeatherRecord::fromHourlyWindMean(new \DateTimeImmutable($hour), $windSin, $windCos));
        $em->persist(WeatherForecastRecord::fromArray([
            'date' => new \DateTimeImmutable($hour),
            'humidity' => '70',
            'pressure' => '1012',
            'temperature' => '18',
            'windDirection' => '230',
            'windSpeed' => '7',
        ]));
        $em->flush();

        $rows = [];
        foreach ($repository->findAllWithIterator() as $row) {
            if ($hour === $row['recorded_hour']) {
                $rows[] = $row;
            }
        }

        $this->assertCount(1, $rows, 'A single hourly-mean row must reach the export despite the six-samples rule.');
        // Whole-degree rounding on the bearing column moves the vector by less than a tenth of a knot.
        $this->assertEqualsWithDelta($windSin, (float) $rows[0]['wind_sin'], 0.02);
        $this->assertEqualsWithDelta($windCos, (float) $rows[0]['wind_cos'], 0.02);
        $this->assertSame('', (string) $rows[0]['humidity'], 'An hourly mean carries no humidity, and none must be invented.');
        $this->assertSame('', (string) $rows[0]['temperature']);
    }

    /**
     * The hourly aggregate only keeps hours with at least six observations, which is what the
     * ten-minute station cadence produces.
     */
    private function persistAnHourOfObservations(EntityManagerInterface $em): void
    {
        $hour = new \DateTimeImmutable(self::HOUR);
        for ($minute = 0; $minute < 60; $minute += 10) {
            $em->persist(LiveWeatherRecord::fromArray([
                'recordedAt' => $hour->modify(sprintf('+%d minutes', $minute)),
                'humidity' => '80',
                'pressure' => '1010',
                'temperature' => '15',
                'windDirection' => '90',
                'windSpeed' => '12',
                'windGusts' => '18',
            ]));
        }

        $em->flush();
    }
}

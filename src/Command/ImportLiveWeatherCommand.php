<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LiveWeatherRecord;
use App\Entity\WeatherForecastRecord;
use App\Repository\LiveWeatherRecordRepository;
use App\Repository\WeatherForecastRecordRepository;
use App\Weather\LiveWeatherComparator;
use App\Weather\LiveWeatherProvider;
use App\Weather\WeatherForecastProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Single recurring weather ingestion: fetches the live observation on every run, refreshes the forecast
 * only when it is stale, then compares the observation against the nearest forecast and the ML
 * correction of it (via {@see LiveWeatherComparator}, which runs the prediction model each time).
 */
#[AsCommand('app:import:live-weather', 'Import live weather, refresh forecast when stale, run prediction')]
#[AsCronTask('*/10 * * * *', 'Europe/Paris')]
final readonly class ImportLiveWeatherCommand
{
    // Refetch the forecast when the newest one is older than this.
    private const string FORECAST_MAX_AGE = '-3 hours';

    public function __construct(
        private LiveWeatherProvider $liveWeatherProvider,
        private WeatherForecastProvider $forecastProvider,
        private LiveWeatherRecordRepository $recordRepository,
        private WeatherForecastRecordRepository $forecastRepository,
        private LiveWeatherComparator $comparator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->refreshForecastIfStale($io);

        $io->info('Importing live weather data...');

        $liveWeather = $this->liveWeatherProvider->get();
        if (null === $liveWeather) {
            $io->error('Error when fetching live weather');

            return Command::FAILURE;
        }

        // Compare the observation against the nearest forecast and the ML correction of it.
        $comparison = $this->comparator->compare(
            $liveWeather->windSpeed,
            $liveWeather->windDirection,
            $this->forecastRepository->findNearest($liveWeather->updatedAt),
        );

        $this->recordRepository->save(LiveWeatherRecord::fromLiveWeather($liveWeather, $comparison));

        $io->success('Live weather imported for date '.$liveWeather->updatedAt->format('c'));

        return Command::SUCCESS;
    }

    private function refreshForecastIfStale(SymfonyStyle $io): void
    {
        $latest = $this->forecastRepository->findLatestCreatedAt();
        if (null !== $latest && $latest > new \DateTimeImmutable(self::FORECAST_MAX_AGE)) {
            return;
        }

        $io->info('Refreshing weather forecast...');

        $count = 0;
        foreach ($this->forecastProvider->get() as $forecast) {
            $this->forecastRepository->saveDeferred(WeatherForecastRecord::fromWeatherForecast($forecast));
            ++$count;
        }
        $this->forecastRepository->saveBatch();

        $io->success(sprintf('%d forecast records imported.', $count));
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LiveWeatherRecord;
use App\Repository\LiveWeatherRecordRepository;
use App\Repository\WeatherForecastRecordRepository;
use App\Weather\LiveWeatherComparator;
use App\Weather\LiveWeatherProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand('app:import:live-weather', 'Import live weather data')]
#[AsCronTask('*/5 * * * *', 'Europe/Paris')]
final readonly class ImportLiveWeatherCommand
{
    public function __construct(
        private LiveWeatherProvider $liveWeatherProvider,
        private LiveWeatherRecordRepository $recordRepository,
        private WeatherForecastRecordRepository $forecastRepository,
        private LiveWeatherComparator $comparator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
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
}

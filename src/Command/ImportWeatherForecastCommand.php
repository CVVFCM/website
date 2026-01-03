<?php

namespace App\Command;

use App\Entity\WeatherForecastRecord;
use App\Repository\WeatherForecastRecordRepository;
use App\Weather\WeatherForecastProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand('app:import:weather-forecast', 'Import weather forecast data')]
#[AsCronTask('30 */3 * * *', 'Europe/Paris')]
final readonly class ImportWeatherForecastCommand
{
    public function __construct(
        private WeatherForecastProvider $forecastProvider,
        private WeatherForecastRecordRepository $recordRepository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->info('Importing live weather data...');

        $forecasts = $this->forecastProvider->get();
        foreach ($forecasts as $forecast) {
            $this->recordRepository->save(WeatherForecastRecord::fromWeatherForecast($forecast));

            $io->success('Live weather imported for date '.$forecast->date->format('c'));
        }

        return Command::SUCCESS;
    }
}

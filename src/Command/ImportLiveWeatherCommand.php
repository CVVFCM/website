<?php

namespace App\Command;

use App\Entity\LiveWeatherRecord;
use App\Repository\LiveWeatherRecordRepository;
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

        $this->recordRepository->save(LiveWeatherRecord::fromLiveWeather($liveWeather));

        $io->success('Live weather imported for date '.$liveWeather->updatedAt->format('c'));

        return Command::SUCCESS;
    }
}

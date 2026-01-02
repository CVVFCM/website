<?php

namespace App\Command;

use App\Repository\LiveWeatherRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:export:live-weather-for-ml', 'Export live weather data for machine learning')]
final readonly class ExportLiveWeatherForMLCommand
{
    public function __construct(
        private LiveWeatherRecordRepository $liveWeatherRecordRepository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io = $io->getErrorStyle();
        $output = new \SplFileObject('php://output', 'w');

        $headerPrinted = false;
        foreach ($this->liveWeatherRecordRepository->findAllWithIterator() as $line) {
            if (!$headerPrinted) {
                $output->fputcsv(array_keys($line));

                $headerPrinted = true;
            }

            $output->fputcsv($line);
        }

        return Command::SUCCESS;
    }
}

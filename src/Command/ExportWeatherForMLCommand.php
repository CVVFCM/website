<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\LiveWeatherRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:export:weather-for-ml', 'Export weather data for machine learning')]
final readonly class ExportWeatherForMLCommand
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

            $line['recorded_hour'] = new \DateTimeImmutable($line['recorded_hour'], new \DateTimeZone('Europe/Paris'))->getTimestamp();
            $output->fputcsv($line);
        }

        if (!$headerPrinted) {
            $io->error('No weather data found.');
        }

        return Command::SUCCESS;
    }
}

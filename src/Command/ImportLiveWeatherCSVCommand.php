<?php

namespace App\Command;

use App\Entity\LiveWeatherRecord;
use App\Repository\LiveWeatherRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

#[AsCommand('app:import:live-weather-csv', 'Import live weather data from a CSV file')]
final readonly class ImportLiveWeatherCSVCommand
{
    private const array COLUMN_MAPPING = [
        'Date' => 'recordedAt',
        'Température' => 'temperature',
        'Humidité' => 'humidity',
        'Pression atmosphérique' => 'pressure',
        'Vitesse moyenne du vent' => 'windSpeed',
        'Rafale maximale de vent' => 'windGusts',
        'Direction moyenne du vent' => 'windDirection',
    ];

    public function __construct(
        private LiveWeatherRecordRepository $recordRepository,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $finder = Finder::create()
            ->files()
            ->in($this->projectDir.'/data/weather/live')
            ->name('*.csv')
            ->sortByName();

        foreach ($finder as $fileInfo) {
            $io->section('Importing file: '.$fileInfo->getFilename());

            $mapping = null;
            $file = new \SplFileObject('php://filter/read=convert.iconv.UTF-16LE.UTF-8/resource='.$fileInfo->getRealPath());

            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $file->setCsvControl(';');

            foreach ($file as $row) {
                if (null === $mapping) {
                    $mapping = $this->extractColumnMapping($row);

                    continue;
                }

                $data = [];
                foreach (self::COLUMN_MAPPING as $property) {
                    if (!isset($mapping[$property])) {
                        $io->warning('Mising column for property: '.$property);

                        continue 2;
                    }

                    $data[$property] = $row[$mapping[$property]]
                        |> (fn (string $s) => str_replace(',', '.', $s))
                        |> (fn (string $s) => str_replace("\u{A0}", '', $s));
                }

                if (!$data['humidity'] || !$data['pressure']) {
                    $io->warning('Skipping record with invalid data: '.json_encode($data));

                    continue;
                }

                $data['recordedAt'] = \DateTimeImmutable::createFromFormat('d/m/Y H:i:s', $data['recordedAt']);
                $this->recordRepository->saveDeferred(LiveWeatherRecord::fromArray($data));
            }
        }

        $this->recordRepository->saveBatch();

        return Command::SUCCESS;
    }

    private function extractColumnMapping(array $row): array
    {
        $columns = [];
        foreach ($row as $index => $columnName) {
            foreach (self::COLUMN_MAPPING as $expectedColumnName => $expectedProperty) {
                if (str_starts_with($columnName, $expectedColumnName)) {
                    $columns[$expectedProperty] = $index;

                    break;
                }
            }
        }

        return $columns;
    }
}

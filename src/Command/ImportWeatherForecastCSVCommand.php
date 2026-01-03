<?php

namespace App\Command;

use App\Entity\WeatherForecastRecord;
use App\Repository\WeatherForecastRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

#[AsCommand('app:import:weather-forecast-csv', 'Import live weather data from a CSV file')]
final readonly class ImportWeatherForecastCSVCommand
{
    private const array COLUMN_MAPPING = [
        'time' => 'date',
        'temperature_2m' => 'temperature',
        'relative_humidity_2m' => 'humidity',
        'pressure_msl' => 'pressure',
        'wind_speed_10m' => 'windSpeed',
        'wind_direction_10m' => 'windDirection',
    ];

    public function __construct(
        private WeatherForecastRecordRepository $recordRepository,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $finder = Finder::create()
            ->files()
            ->in($this->projectDir.'/data/weather/past_forecast')
            ->name('*.csv')
            ->sortByName();

        foreach ($finder as $fileInfo) {
            $io->section('Importing file: '.$fileInfo->getFilename());

            $mapping = null;
            $file = new \SplFileObject($fileInfo->getRealPath());
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $file->setCsvControl(',');

            $i = 0;
            foreach ($file as $row) {
                if ($i++ < 3) {
                    continue;
                }

                if (null === $mapping) {
                    $mapping = $this->extractColumnMapping($row);

                    continue;
                }

                $data = [];
                foreach (self::COLUMN_MAPPING as $property) {
                    if (!isset($row[$mapping[$property]])) {
                        $io->warning(
                            sprintf(
                                'Missing column for property: %s. File: %s, Line: %d',
                                $property,
                                $fileInfo->getFilename(),
                                $i + 1,
                            )
                        );

                        continue 2;
                    }

                    $data[$property] = $row[$mapping[$property]];
                }

                if ('NaN' === $data['humidity'] || 'NaN' === $data['pressure']) {
                    $io->warning('Skipping record with invalid data: '.json_encode($data));

                    continue;
                }

                $data['date'] = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $data['date'], new \DateTimeZone('UTC'))
                    ->setTimezone(new \DateTimeZone('Europe/Paris'));
                $this->recordRepository->saveDeferred(WeatherForecastRecord::fromArray($data));
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

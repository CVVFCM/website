<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LiveWeatherRecord;
use App\Repository\LiveWeatherRecordRepository;
use App\Repository\WeatherForecastRecordRepository;
use App\Weather\LiveWeatherComparator;
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
        'Température (°C)' => 'temperature',
        'Humidité' => 'humidity',
        'Pression atmosphérique' => 'pressure',
        'Vitesse moyenne du vent' => 'windSpeed',
        'Rafale maximale de vent' => 'windGusts',
        'Direction moyenne du vent' => 'windDirection',
    ];

    public function __construct(
        private LiveWeatherRecordRepository $recordRepository,
        private WeatherForecastRecordRepository $forecastRepository,
        private LiveWeatherComparator $comparator,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    /**
     * @psalm-suppress MixedArrayAccess
     * @psalm-suppress MixedArrayOffset
     * @psalm-suppress MixedAssignment
     */
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

            foreach ($file as $i => $row) {
                if (null === $mapping) {
                    if (!\is_array($row)) {
                        $io->warning('Skipping invalid CSV file: '.$fileInfo->getFilename());

                        continue 2;
                    }

                    /** @var array<int, string> $row */
                    $mapping = $this->extractColumnMapping($row);

                    continue;
                }

                $data = [];
                foreach (self::COLUMN_MAPPING as $property) {
                    if (!isset($mapping[$property])) {
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

                    if ('recordedAt' === $property) {
                        $date = \DateTimeImmutable::createFromFormat(
                            'd/m/Y H:i:s',
                            (string) $row[$mapping[$property]],
                            new \DateTimeZone('Europe/Paris'),
                        );

                        if (false === $date) {
                            $io->warning('Skipping record with invalid date line '.($i + 1));

                            continue;
                        }

                        $data['recordedAt'] = $date;

                        continue;
                    }

                    $data[$property] = $row[$mapping[$property]]
                        |> (fn (string $s): string => str_replace(',', '.', $s))
                        |> (fn (string $s): string => str_replace("\u{A0}", '', $s));
                }

                /**
                 * @var array{
                 *     recordedAt: \DateTimeImmutable,
                 *     humidity: string,
                 *     pressure: string,
                 *     temperature: string,
                 *     windDirection: string,
                 *     windSpeed: string,
                 *     windGusts: string,
                 * } $data
                 */
                if (!$data['humidity'] || !$data['pressure']) {
                    $io->warning('Skipping record with invalid data: '.json_encode($data, JSON_THROW_ON_ERROR));

                    continue;
                }

                $comparison = $this->comparator->compare(
                    (float) $data['windSpeed'],
                    (int) $data['windDirection'],
                    $this->forecastRepository->findNearest($data['recordedAt']),
                );

                $this->recordRepository->saveDeferred(LiveWeatherRecord::fromArray($data, $comparison));
            }
        }

        $this->recordRepository->saveBatch();

        return Command::SUCCESS;
    }

    /**
     * @param array<int, string> $row
     *
     * @return array<string, int>
     */
    private function extractColumnMapping(array $row): array
    {
        $columns = [];
        foreach ($row as $index => $columnName) {
            foreach (self::COLUMN_MAPPING as $expectedColumnName => $expectedProperty) {
                if (str_contains($columnName, $expectedColumnName)) {
                    $columns[$expectedProperty] = $index;

                    break;
                }
            }
        }

        return $columns;
    }
}

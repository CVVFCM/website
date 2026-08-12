<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LiveWeatherRecord;
use App\Repository\LiveWeatherRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * Imports historical hours whose wind is already averaged, from spreadsheet exports that carry the
 * (sin·speed, cos·speed) projection and a unix timestamp.
 *
 * These files exist because part of the station history was consolidated in a spreadsheet before the
 * site stored anything. They hold one row per hour instead of the station's ten-minute samples, and
 * only the wind survived the round trip intact — the other columns came back coerced into date
 * serials, so they are not read at all. The rows land flagged as hourly means, which is what lets
 * them through the six-sample threshold in {@see LiveWeatherRecordRepository::findAllWithIterator()}.
 *
 * Hours already present in the table are skipped, so a second run is a no-op rather than a
 * duplication — unlike the sibling CSV importers.
 */
#[AsCommand('app:import:hourly-wind-csv', 'Import already-averaged hourly wind observations from CSV files')]
final readonly class ImportHourlyWindCSVCommand
{
    private const string TIMESTAMP_COLUMN = 'recorded_hour';
    private const string WIND_SIN_COLUMN = 'wind_sin';
    private const string WIND_COS_COLUMN = 'wind_cos';

    public function __construct(
        private LiveWeatherRecordRepository $recordRepository,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $directory = $this->projectDir.'/data/weather/hourly_wind';
        if (!is_dir($directory)) {
            $io->warning(sprintf('No %s directory, nothing to import.', $directory));

            return Command::SUCCESS;
        }

        $finder = Finder::create()->files()->in($directory)->name('*.csv')->sortByName();
        $known = $this->recordRepository->findHoursAlreadyRecorded();
        $imported = $skipped = 0;

        foreach ($finder as $fileInfo) {
            $io->section('Importing file: '.$fileInfo->getFilename());

            $file = new \SplFileObject($fileInfo->getRealPath());
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

            $columns = null;
            foreach ($file as $row) {
                if (!\is_array($row) || null === $row[0]) {
                    continue;
                }

                if (null === $columns) {
                    $columns = $this->extractColumnMapping($row);
                    if (null === $columns) {
                        $io->warning(sprintf(
                            'File %s has no %s/%s/%s header, skipping it.',
                            $fileInfo->getFilename(),
                            self::TIMESTAMP_COLUMN,
                            self::WIND_SIN_COLUMN,
                            self::WIND_COS_COLUMN,
                        ));

                        continue 2;
                    }

                    continue;
                }

                $timestamp = $this->parseNumber($row[$columns[self::TIMESTAMP_COLUMN]] ?? null);
                $windSin = $this->parseNumber($row[$columns[self::WIND_SIN_COLUMN]] ?? null);
                $windCos = $this->parseNumber($row[$columns[self::WIND_COS_COLUMN]] ?? null);

                if (null === $timestamp || null === $windSin || null === $windCos) {
                    ++$skipped;

                    continue;
                }

                // The spreadsheet repeats hours; the table must not.
                $hour = (new \DateTimeImmutable('@'.(int) round($timestamp)))
                    ->setTimezone(new \DateTimeZone('Europe/Paris'));
                $key = $hour->format('Y-m-d H:00:00');
                if (isset($known[$key])) {
                    ++$skipped;

                    continue;
                }

                $known[$key] = true;
                $this->recordRepository->saveDeferred(
                    LiveWeatherRecord::fromHourlyWindMean($hour, $windSin, $windCos),
                );
                ++$imported;
            }
        }

        $this->recordRepository->saveBatch();
        $io->success(sprintf('%d hourly observations imported, %d skipped.', $imported, $skipped));

        return Command::SUCCESS;
    }

    /**
     * Spreadsheet exports write French numbers: thin or non-breaking thousands separators, comma
     * decimals, and an error marker such as #DIV/0! where a formula could not resolve.
     *
     * Takes mixed because SplFileObject hands back untyped cells, and a docblock narrowing them
     * would be rewritten into an inert comment by php-cs-fixer.
     */
    private function parseNumber(mixed $value): ?float
    {
        if (!\is_string($value)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9,.\-]/u', '', $value) ?? '';
        $cleaned = str_replace(',', '.', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return array<string, array-key>|null
     */
    private function extractColumnMapping(array $row): ?array
    {
        $wanted = [self::TIMESTAMP_COLUMN, self::WIND_SIN_COLUMN, self::WIND_COS_COLUMN];
        $columns = [];
        foreach ($row as $index => $name) {
            if (!\is_string($name)) {
                continue;
            }

            // Strip the byte-order mark a spreadsheet export puts before the first header.
            $name = trim(ltrim($name, "\u{FEFF}"));
            if (\in_array($name, $wanted, true)) {
                $columns[$name] = $index;
            }
        }

        return \count($columns) === \count($wanted) ? $columns : null;
    }
}

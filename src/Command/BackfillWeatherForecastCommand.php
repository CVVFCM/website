<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * Backfills historical open-meteo forecasts month by month and writes them as monthly CSVs in the
 * exact format {@see ImportWeatherForecastCSVCommand} consumes (UTC/GMT times, comma separated,
 * three preamble lines). The regular forecast endpoint only exposes ~92 days, so the dedicated
 * historical-forecast host is used here.
 */
#[AsCommand('app:import:weather-forecast-history', 'Backfill historical weather forecasts as monthly CSV files')]
final readonly class BackfillWeatherForecastCommand
{
    private const string HISTORICAL_BASE_URL = 'https://historical-forecast-api.open-meteo.com/v1/';
    private const int MAX_ATTEMPTS = 5;

    /** Columns written, mirroring the manual open-meteo export the importer already parses. */
    private const array HOURLY_VARIABLES = [
        'temperature_2m',
        'wind_speed_10m',
        'wind_direction_10m',
        'pressure_msl',
        'relative_humidity_2m',
    ];
    private const array COLUMN_HEADERS = [
        'time',
        'temperature_2m (°C)',
        'wind_speed_10m (kn)',
        'wind_direction_10m (°)',
        'pressure_msl (hPa)',
        'relative_humidity_2m (%)',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private float $latitude = 49.8712,
        private float $longitude = 4.5947,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('First day to fetch (inclusive), Y-m-d')]
        string $start = '2025-11-15',
        #[Option('Last day to fetch (inclusive), Y-m-d')]
        string $end = '2026-07-10',
        #[Option('open-meteo model to query')]
        string $model = 'meteofrance_seamless',
    ): int {
        $startDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $endDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (false === $startDate || false === $endDate || $startDate > $endDate) {
            $io->error('Invalid --start / --end range.');

            return Command::FAILURE;
        }

        $client = HttpClient::createForBaseUri(self::HISTORICAL_BASE_URL);
        $targetDir = $this->projectDir.'/data/weather/past_forecast';

        // Iterate calendar months so each request payload stays small (limits rate-limiting) and each
        // month maps to exactly one output file.
        $cursor = $startDate->modify('first day of this month');
        $lastMonth = $endDate->modify('first day of this month');

        while ($cursor <= $lastMonth) {
            $monthStart = max($cursor, $startDate);
            $monthEnd = min($cursor->modify('last day of this month'), $endDate);

            $io->section(sprintf('Fetching %s (%s → %s)', $cursor->format('Y-m'), $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')));

            $hourly = $this->fetchMonth($client, $io, $model, $monthStart, $monthEnd);
            if (null === $hourly) {
                return Command::FAILURE;
            }

            $written = $this->writeMonthlyCsv($targetDir, $cursor->format('Y-m'), $hourly);
            $io->success(sprintf('%d hourly rows written to open-meteo-%s.csv', $written, $cursor->format('Y-m')));

            $cursor = $cursor->modify('first day of next month');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{time: list<string>, temperature_2m: list<float|null>, wind_speed_10m: list<float|null>, wind_direction_10m: list<int|null>, pressure_msl: list<float|null>, relative_humidity_2m: list<int|null>}|null
     */
    private function fetchMonth(
        \Symfony\Contracts\HttpClient\HttpClientInterface $client,
        SymfonyStyle $io,
        string $model,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): ?array {
        $query = http_build_query([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'hourly' => implode(',', self::HOURLY_VARIABLES),
            'wind_speed_unit' => 'kn',
            // No timezone → GMT, matching the manual export the importer parses as UTC then shifts.
            'models' => $model,
            'start_date' => $from->format('Y-m-d'),
            'end_date' => $to->format('Y-m-d'),
        ]);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $response = $client->request('GET', 'forecast?'.$query);

                /** @var array{hourly?: array<string, list<float|int|null>>, error?: bool, reason?: string} $data */
                $data = $response->toArray();

                if (!isset($data['hourly']['time'])) {
                    $io->error('Unexpected response: '.($data['reason'] ?? 'no hourly data'));

                    return null;
                }

                /** @var array{time: list<string>, temperature_2m: list<float|null>, wind_speed_10m: list<float|null>, wind_direction_10m: list<int|null>, pressure_msl: list<float|null>, relative_humidity_2m: list<int|null>} $hourly */
                $hourly = $data['hourly'];

                return $hourly;
            } catch (HttpExceptionInterface $e) {
                $status = $e->getResponse()->getStatusCode();
                if ((429 === $status || $status >= 500) && $attempt < self::MAX_ATTEMPTS) {
                    $wait = 2 ** $attempt;
                    $io->warning(sprintf('HTTP %d, retry %d/%d in %ds', $status, $attempt, self::MAX_ATTEMPTS, $wait));
                    sleep($wait);

                    continue;
                }

                $io->error(sprintf('HTTP %d: %s', $status, $e->getMessage()));

                return null;
            }
        }

        return null;
    }

    /**
     * @param array{time: list<string>, temperature_2m: list<float|null>, wind_speed_10m: list<float|null>, wind_direction_10m: list<int|null>, pressure_msl: list<float|null>, relative_humidity_2m: list<int|null>} $hourly
     */
    private function writeMonthlyCsv(string $targetDir, string $month, array $hourly): int
    {
        $file = new \SplFileObject($targetDir.'/open-meteo-'.$month.'.csv', 'w');

        // Three preamble lines, exactly as the manual open-meteo export the importer skips.
        $file->fputcsv(['latitude', 'longitude', 'elevation', 'utc_offset_seconds', 'timezone', 'timezone_abbreviation']);
        $file->fputcsv([$this->latitude, $this->longitude, '', 0, 'GMT', 'GMT']);
        $file->fwrite("\n");
        $file->fputcsv(self::COLUMN_HEADERS);

        $written = 0;
        foreach ($hourly['time'] as $i => $time) {
            $file->fputcsv([
                $time,
                $this->scalar($hourly['temperature_2m'][$i] ?? null),
                $this->scalar($hourly['wind_speed_10m'][$i] ?? null),
                $this->scalar($hourly['wind_direction_10m'][$i] ?? null),
                $this->scalar($hourly['pressure_msl'][$i] ?? null),
                $this->scalar($hourly['relative_humidity_2m'][$i] ?? null),
            ]);
            ++$written;
        }

        return $written;
    }

    private function scalar(float|int|null $value): string
    {
        return null === $value ? 'NaN' : (string) $value;
    }
}

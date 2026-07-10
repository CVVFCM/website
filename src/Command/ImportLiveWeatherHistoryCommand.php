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
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tops up recent live records from Weathercloud's undocumented `device/evolution` endpoint, the only
 * reachable history source. It exposes at best ~24 h of hourly buckets (`period=day`), so this is a
 * convenience gap-filler, NOT a historical backfill.
 *
 * Beware: {@see ExportWeatherForMLCommand} keeps only hours with `COUNT(*) >= 6` sub-hour samples, so
 * a single evolution-derived hourly row per hour does not reach ml.csv. See the warning emitted below.
 */
#[AsCommand('app:import:live-weather-history', 'Top up recent live weather from Weathercloud (last ~24h, hourly)')]
final readonly class ImportLiveWeatherHistoryCommand
{
    private const string EVOLUTION_URL = 'https://app.weathercloud.net/device/evolution';
    private const float MS_TO_KNOTS = 1.94384;

    // Weathercloud numeric variable codes (identified empirically).
    private const int TEMPERATURE = 101;
    private const int HUMIDITY = 201;
    private const int PRESSURE = 701;
    private const int WIND = 541;      // response also carries 521 (gust)
    private const int WIND_GUST = 521;
    private const int WIND_DIRECTION = 641;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LiveWeatherRecordRepository $recordRepository,
        private WeatherForecastRecordRepository $forecastRepository,
        private LiveWeatherComparator $comparator,
        #[Autowire('%env(WEATHER_CLOUD_DEVICE_CODE)%')] private string $deviceCode,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->warning('Weathercloud offers no true history API: this only covers ~last 24h (hourly). These rows do not reach ml.csv (export keeps hours with >=6 sub-hour samples).');

        // sum/samples averages per hourly bucket, keyed by unix timestamp.
        $temperature = $this->scalarSeries(self::TEMPERATURE);
        $humidity = $this->scalarSeries(self::HUMIDITY);
        $pressure = $this->scalarSeries(self::PRESSURE);
        $windAndGust = $this->windSeries();
        $direction = $this->directionSeries();

        if ([] === $temperature) {
            $io->error('No data returned by Weathercloud evolution endpoint.');

            return Command::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        foreach ($temperature as $timestamp => $temp) {
            // Require the full scalar set; skip partial buckets.
            if (!isset($humidity[$timestamp], $pressure[$timestamp], $windAndGust[$timestamp])) {
                continue;
            }

            $recordedAt = (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone('Europe/Paris'));

            if (null !== $this->recordRepository->findOneBy(['recordedAt' => $recordedAt])) {
                ++$skipped;

                continue;
            }

            $windSpeed = $windAndGust[$timestamp]['speed'] * self::MS_TO_KNOTS;
            $windDirection = $direction[$timestamp] ?? 0;
            $comparison = $this->comparator->compare($windSpeed, $windDirection, $this->forecastRepository->findNearest($recordedAt));

            $this->recordRepository->saveDeferred(LiveWeatherRecord::fromArray([
                'recordedAt' => $recordedAt,
                'humidity' => (string) $humidity[$timestamp],
                'pressure' => (string) $pressure[$timestamp],
                'temperature' => (string) $temp,
                'windDirection' => (string) $windDirection,
                'windSpeed' => (string) $windSpeed,
                'windGusts' => (string) ($windAndGust[$timestamp]['gust'] * self::MS_TO_KNOTS),
            ], $comparison));
            ++$imported;
        }

        $this->recordRepository->saveBatch();
        $io->success(sprintf('%d hourly records imported, %d already present.', $imported, $skipped));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, float> bucket timestamp => hourly average
     */
    private function scalarSeries(int $variable): array
    {
        $series = [];
        foreach ($this->buckets($variable) as $timestamp => $codes) {
            /** @var array{samples?: int, stats?: array{sum?: float|int}} $entry */
            $entry = $codes[$variable] ?? [];
            $samples = $entry['samples'] ?? 0;
            $sum = $entry['stats']['sum'] ?? null;
            if ($samples > 0 && \is_numeric($sum)) {
                $series[$timestamp] = (float) $sum / (float) $samples;
            }
        }

        return $series;
    }

    /**
     * @return array<int, array{speed: float, gust: float}>
     */
    private function windSeries(): array
    {
        $series = [];
        foreach ($this->buckets(self::WIND) as $timestamp => $codes) {
            /** @var array{samples?: int, stats?: array{sum?: float|int}} $avg */
            $avg = $codes[self::WIND] ?? [];
            /** @var array{stats?: array{max?: float|int}} $gust */
            $gust = $codes[self::WIND_GUST] ?? [];
            $samples = $avg['samples'] ?? 0;
            $sum = $avg['stats']['sum'] ?? null;
            if ($samples > 0 && \is_numeric($sum)) {
                $speed = (float) $sum / (float) $samples;
                $gustMax = $gust['stats']['max'] ?? null;
                $series[$timestamp] = [
                    'speed' => $speed,
                    'gust' => \is_numeric($gustMax) ? (float) $gustMax : $speed,
                ];
            }
        }

        return $series;
    }

    /**
     * Direction is stored as a wind vector (stats.sum.x / .y); recover the meteorological bearing,
     * matching the sin/cos convention used by the export SQL.
     *
     * @return array<int, int>
     */
    private function directionSeries(): array
    {
        $series = [];
        foreach ($this->buckets(self::WIND_DIRECTION) as $timestamp => $codes) {
            /** @var array{stats?: array{sum?: array{x?: float, y?: float}}} $entry */
            $entry = $codes[self::WIND_DIRECTION] ?? [];
            $x = $entry['stats']['sum']['x'] ?? null;
            $y = $entry['stats']['sum']['y'] ?? null;
            if (\is_numeric($x) && \is_numeric($y)) {
                $series[$timestamp] = (int) round(rad2deg(atan2($x, $y)) + 360.0) % 360;
            }
        }

        return $series;
    }

    /**
     * @return array<int, array<int, array<string, mixed>>> bucket timestamp => variable code => stats
     */
    private function buckets(int $variable): array
    {
        try {
            $response = $this->httpClient->request('POST', self::EVOLUTION_URL, [
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'User-Agent' => 'Mozilla/5.0',
                    'Referer' => 'https://app.weathercloud.net/d'.$this->deviceCode,
                ],
                'body' => http_build_query(['device' => $this->deviceCode, 'variable' => $variable, 'period' => 'day']),
                'verify_peer' => false,
                'timeout' => 15,
            ]);

            /** @var array{data?: array{values?: array<string, array<int, array<string, mixed>>>}} $data */
            $data = $response->toArray();
        } catch (ExceptionInterface) {
            return [];
        }

        $buckets = [];
        foreach ($data['data']['values'] ?? [] as $timestamp => $codes) {
            $buckets[(int) $timestamp] = $codes;
        }

        return $buckets;
    }
}

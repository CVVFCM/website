<?php

declare(strict_types=1);

namespace App\Weather;

use App\DTO\LiveWeather;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @psalm-type WeatherCloudData = null|array{
 *   last_update: int,
 *   hum_current: array{0: int, 1: float},
 *   hum_day_min: array{0: int, 1: float},
 *   hum_day_max: array{0: int, 1: float},
 *   bar_current: array{0: int, 1: float},
 *   bar_day_min: array{0: int, 1: float},
 *   bar_day_max: array{0: int, 1: float},
 *   rainrate_current: array{0: int, 1: float},
 *   rain_day_total: array{0: int, 1: float},
 *   solarrad_current: array{0: int, 1: int},
 *   temp_current: array{0: int, 1: float},
 *   temp_day_min: array{0: int, 1: float},
 *   temp_day_max: array{0: int, 1: float},
 *   wdir_current: array{0: int, 1: int},
 *   wdiravg_current: array{0: int, 1: int},
 *   wspd_current: array{0: int, 1: float},
 *   wspdhi_current: array{0: int, 1: float},
 *   wspdavg_current: array{0: int, 1: float},
 *   wspd_day_min: array{0: int, 1: float},
 *   wspd_day_max: array{0: int, 1: float}
 * }
 */
#[AsAlias(LiveWeatherProvider::class)]
final readonly class WeatherCloudProvider implements LiveWeatherProvider
{
    private const string WEATHER_URL = 'https://app.weathercloud.net/device/stats?code=%s';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(WEATHER_CLOUD_DEVICE_CODE)%')]
        private string $weatherCloudDeviceCode,
    ) {
    }

    #[\Override]
    public function get(): ?LiveWeather
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                sprintf(self::WEATHER_URL, $this->weatherCloudDeviceCode),
                [
                    'timeout' => 5,
                    'headers' => [
                        'Accept' => 'application/json',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ],
                    'verify_peer' => false,
                ],
            );

            /** @var WeatherCloudData $data */
            $data = $response->toArray();

            return $this->hydrateDTO($data);
        } catch (\Exception $e) {
            $this->logger->error('Error when fetching live weather : '.$e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    #[\Override]
    public function getExternalLink(): string
    {
        return sprintf('https://app.weathercloud.net/d%s', $this->weatherCloudDeviceCode);
    }

    /**
     * @param WeatherCloudData $data
     */
    private function hydrateDTO(?array $data): LiveWeather
    {
        if (!$data) {
            throw new \InvalidArgumentException('Empty data received from Weathercloud');
        }

        $updatedAt = \DateTimeImmutable::createFromFormat('U', (string) $data['last_update'], new \DateTimeZone('UTC'));
        assert(false !== $updatedAt);

        $updatedAt = $updatedAt->setTimezone(new \DateTimeZone('+01:00'));

        $weather = new LiveWeather();
        $weather->updatedAt = $updatedAt;
        $weather->humidity = $data['hum_current'][1] / 100.;
        $weather->humidityMin = $data['hum_day_min'][1] / 100.;
        $weather->humidityMax = $data['hum_day_max'][1] / 100.;
        $weather->pressure = $data['bar_current'][1];
        $weather->pressureMin = $data['bar_day_min'][1];
        $weather->pressureMax = $data['bar_day_max'][1];
        $weather->rainRate = $data['rainrate_current'][1];
        $weather->rainTotal = $data['rain_day_total'][1];
        $weather->solarRadiation = (int) round($data['solarrad_current'][1]);
        $weather->temperature = $data['temp_current'][1];
        $weather->temperatureMin = $data['temp_day_min'][1];
        $weather->temperatureMax = $data['temp_day_max'][1];
        $weather->windDirection = $data['wdir_current'][1];
        $weather->windDirectionAverage = $data['wdiravg_current'][1];
        $weather->windSpeed = $this->convertToKnots($data['wspd_current'][1]);
        $weather->windGusts = $this->convertToKnots($data['wspdhi_current'][1]);
        $weather->windSpeedAverage = $this->convertToKnots($data['wspdavg_current'][1]);
        $weather->windSpeedMin = $this->convertToKnots($data['wspd_day_min'][1]);
        $weather->windSpeedMax = $this->convertToKnots($data['wspd_day_max'][1]);

        return $weather;
    }

    private function convertToKnots(float $meterPerSeconds): float
    {
        return $meterPerSeconds * 1.94384;
    }
}

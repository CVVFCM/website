<?php

declare(strict_types=1);

namespace App\Weather;

use App\DTO\WeatherForecast;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

#[AsAlias(WeatherForecastProvider::class)]
final readonly class OpenMeteoAromeWeatherForecastProvider implements WeatherForecastProvider
{
    private const string AROME_BASE_URL = 'https://api.open-meteo.com/v1/';

    public function __construct(
        private LoggerInterface $logger,
        private float $latitude = 49.8712,
        private float $longitude = 4.5947,
    ) {
    }

    #[\Override]
    public function get(): array
    {
        try {
            $client = HttpClient::createForBaseUri(self::AROME_BASE_URL);
            $response = $client->request(
                'GET',
                strtr(
                    'forecast?latitude=::latitude::&longitude=::longitude::&hourly=::data_types::&wind_speed_unit=kn&timezone=UTC&models=meteofrance_seamless&start_hour=::start_hour::&end_hour=::end_hour::',
                    [
                        '::latitude::' => $this->latitude,
                        '::longitude::' => $this->longitude,
                        '::data_types::' => 'temperature_2m,relative_humidity_2m,pressure_msl,precipitation,wind_speed_10m,wind_direction_10m',
                        '::start_hour::' => date('Y-m-d\TH:00'),
                        '::end_hour::' => new \DateTimeImmutable('tomorrow 23:00')->format('Y-m-d\TH:00'),
                    ],
                ),
            );

            /**
             * @var array{
             *      hourly: array{
             *          time: string[],
             *          temperature_2m: float[],
             *          precipitation: float[],
             *          wind_speed_10m: float[],
             *          wind_direction_10m: int[],
             *          pressure_msl: float[],
             *          relative_humidity_2m: int[],
             *      }
             * } $arrayResponse
             */
            $arrayResponse = $response->toArray();
            $forecasts = [];
            foreach ($arrayResponse['hourly']['time'] as $i => $time) {
                $forecasts[] = new WeatherForecast(
                    new \DateTimeImmutable($time),
                    $arrayResponse['hourly']['temperature_2m'][$i],
                    $arrayResponse['hourly']['pressure_msl'][$i],
                    $arrayResponse['hourly']['relative_humidity_2m'][$i],
                    $arrayResponse['hourly']['precipitation'][$i],
                    $arrayResponse['hourly']['wind_speed_10m'][$i],
                    $arrayResponse['hourly']['wind_direction_10m'][$i],
                );
            }

            return $forecasts;
        } catch (HttpExceptionInterface $e) {
            $this->logger->error(
                'Error while fetching weather data',
                ['exception' => $e, 'response_content' => $e->getResponse()->getContent(false)],
            );

            return [];
        }
    }
}

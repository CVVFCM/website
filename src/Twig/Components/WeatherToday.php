<?php

namespace App\Twig\Components;

use App\DTO\WeatherForecast;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class WeatherToday
{
    private const string AROME_BASE_URL = 'https://api.open-meteo.com/v1/';

    public float $latitude = 49.8712;
    public float $longitude = 4.5947;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getData(): array
    {
        return $this->cache->get('weather_today', function (CacheItemInterface $item) {
            $item->expiresAfter(3600);

            try {
                $client = HttpClient::createForBaseUri(self::AROME_BASE_URL);
                $response = $client->request(
                    'GET',
                    strtr(
                        'forecast?latitude=::latitude::&longitude=::longitude::&hourly=::data_types::&wind_speed_unit=kn&timezone=Europe%2FParis&models=meteofrance_seamless&start_hour=::start_hour::&end_hour=::end_hour::',
                        [
                            '::latitude::' => $this->latitude,
                            '::longitude::' => $this->longitude,
                            '::data_types::' => 'temperature_2m,precipitation,wind_speed_10m,wind_direction_10m',
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
                 *      }
                 * } $arrayResponse
                 */
                $arrayResponse = $response->toArray();
                $forecasts = [];
                foreach ($arrayResponse['hourly']['time'] as $i => $time) {
                    if (0 !== $i && !in_array(new \DateTimeImmutable($time)->format('H'), array_keys(WeatherForecast::HOURS))) {
                        continue;
                    }

                    $forecasts[] = new WeatherForecast(
                        new \DateTimeImmutable($time),
                        $arrayResponse['hourly']['temperature_2m'][$i],
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
        });
    }
}

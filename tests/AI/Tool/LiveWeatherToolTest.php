<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\LiveWeatherTool;
use App\DTO\LiveWeather;
use App\Weather\LiveWeatherProvider;
use PHPUnit\Framework\TestCase;

final class LiveWeatherToolTest extends TestCase
{
    public function testItMapsTheFullStation(): void
    {
        $weather = new LiveWeather();
        $weather->updatedAt = new \DateTimeImmutable('2026-07-02 14:30:00');
        $weather->temperature = 22.34;
        $weather->temperatureMin = 15.0;
        $weather->temperatureMax = 24.9;
        $weather->humidity = 0.62;
        $weather->humidityMin = 0.4;
        $weather->humidityMax = 0.8;
        $weather->pressure = 1013.25;
        $weather->pressureMin = 1010.0;
        $weather->pressureMax = 1015.0;
        $weather->rainRate = 0.0;
        $weather->rainTotal = 1.2;
        $weather->solarRadiation = 540;
        $weather->windDirection = 135;
        $weather->windDirectionAverage = 90;
        $weather->windSpeed = 7.4;
        $weather->windGusts = 12.6;
        $weather->windSpeedAverage = 6.2;
        $weather->windSpeedMin = 1.0;
        $weather->windSpeedMax = 14.0;

        $provider = $this->createStub(LiveWeatherProvider::class);
        $provider->method('get')->willReturn($weather);
        $provider->method('getExternalLink')->willReturn('https://app.weathercloud.net/d123');

        $result = (new LiveWeatherTool($provider))();

        $this->assertSame('2026-07-02 14:30:00', $result['mis_a_jour']);
        $this->assertSame(22.3, $result['temperature']['actuelle_celsius']);
        $this->assertSame(62.0, $result['humidite']['actuelle_pourcent']);
        $this->assertSame(1013.3, $result['pression']['actuelle_hpa']);
        $this->assertSame(1.2, $result['pluie']['total_jour_mm']);
        $this->assertSame(540, $result['radiation_solaire_w_m2']);
        $this->assertSame(7, $result['vent']['vitesse_noeuds']);
        $this->assertSame(13, $result['vent']['rafales_noeuds']);
        $this->assertSame('Sud-Est', $result['vent']['direction']);
        $this->assertSame('Est', $result['vent']['direction_moyenne']);
        $this->assertSame('https://app.weathercloud.net/d123', $result['lien_station']);
    }

    public function testStationDownYieldsError(): void
    {
        $provider = $this->createStub(LiveWeatherProvider::class);
        $provider->method('get')->willReturn(null);

        $this->assertSame(
            ['erreur' => 'Station météo indisponible pour le moment.'],
            (new LiveWeatherTool($provider))(),
        );
    }
}

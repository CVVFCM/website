<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\WeatherForecastTool;
use App\DTO\WeatherForecast;
use App\Weather\WeatherForecastProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

final class WeatherForecastToolTest extends TestCase
{
    protected function setUp(): void
    {
        // 2026-07-02 in Paris.
        Clock::set(new MockClock(new \DateTimeImmutable('2026-07-02 08:00:00', new \DateTimeZone('Europe/Paris'))));
    }

    protected function tearDown(): void
    {
        // NativeClock, not `new Clock()`: an inner-less Clock delegates to the global
        // clock — itself here — and recurses infinitely.
        Clock::set(new NativeClock());
    }

    public function testDefaultReturnsTodayAbstractPerMoment(): void
    {
        $result = $this->tool()(null, null, null);

        $this->assertArrayNotHasKey('erreur', $result);
        $this->assertSame('2026-07-02', $result['date']);
        $this->assertSame(
            ['matin', 'midi', 'apres-midi', 'soiree'],
            array_column($result['moments'], 'moment'),
        );
    }

    public function testMorningAbstractAggregatesItsHours(): void
    {
        $result = $this->tool()(null, 'matin', null);

        $this->assertCount(1, $result['moments']);
        $matin = $result['moments'][0];

        $this->assertSame('matin', $matin['moment']);
        $this->assertSame('06h-11h', $matin['heures']);
        // temperature = 15 + hour * 0.5, hours 6..10.
        $this->assertSame(18.0, $matin['temperature_min']);
        $this->assertSame(20.0, $matin['temperature_max']);
        // windSpeed = hour → mean of 6..10 = 8, max 10.
        $this->assertSame(8, $matin['vent_moyen_noeuds']);
        $this->assertSame(10, $matin['vent_max_noeuds']);
        // weatherCode 1 for hours < 9, 61 for 9 and 10 → 3 × Éclaircies vs 2 × Pluie.
        $this->assertSame('Éclaircies', $matin['condition']);
        $this->assertSame(0.0, $matin['precipitation_totale_mm']);
        $this->assertSame(60, $matin['humidite_moyenne_pourcent']);
    }

    public function testEveningAbstractSumsPrecipitation(): void
    {
        $result = $this->tool()(null, 'soiree', null);

        // precipitation = 0.5 mm per hour from 18h, moment covers 18..21.
        $this->assertSame(2.0, $result['moments'][0]['precipitation_totale_mm']);
    }

    public function testPreciseHourReturnsFullHourlyDetail(): void
    {
        $result = $this->tool()(null, 'matin', 15);

        $this->assertArrayNotHasKey('moments', $result);
        $this->assertCount(1, $result['previsions']);
        $this->assertSame('15h', $result['previsions'][0]['heure']);
        $this->assertArrayHasKey('pression_hpa', $result['previsions'][0]);
    }

    public function testTomorrowIsAvailable(): void
    {
        $result = $this->tool()('2026-07-03', 'midi', null);

        $this->assertSame('2026-07-03', $result['date']);
        $this->assertSame(['midi'], array_column($result['moments'], 'moment'));
    }

    public function testDateBeyondTomorrowIsRejected(): void
    {
        $result = $this->tool()('2026-07-05', null, null);

        $this->assertSame(['erreur' => "Prévisions disponibles uniquement pour aujourd'hui et demain."], $result);
    }

    public function testEmptyProviderYieldsError(): void
    {
        $provider = $this->createStub(WeatherForecastProvider::class);
        $provider->method('get')->willReturn([]);

        $result = (new WeatherForecastTool($provider))(null, null, null);

        $this->assertSame(['erreur' => 'Prévisions indisponibles pour le moment.'], $result);
    }

    private function tool(): WeatherForecastTool
    {
        $forecasts = [];
        foreach (['2026-07-02', '2026-07-03'] as $day) {
            for ($hour = 0; $hour < 24; ++$hour) {
                $forecasts[] = new WeatherForecast(
                    new \DateTimeImmutable(sprintf('%s %02d:00:00', $day, $hour), new \DateTimeZone('Europe/Paris')),
                    15.0 + $hour * 0.5,
                    1013.2,
                    60,
                    $hour >= 18 ? 0.5 : 0.0,
                    (float) $hour,
                    135,
                    $hour < 9 ? 1 : 61,
                );
            }
        }

        $provider = $this->createStub(WeatherForecastProvider::class);
        $provider->method('get')->willReturn($forecasts);

        return new WeatherForecastTool($provider);
    }
}

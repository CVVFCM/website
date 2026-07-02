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

    public function testDefaultReturnsWholeTodayForecast(): void
    {
        $result = $this->tool()(null, null, null);

        $this->assertArrayNotHasKey('erreur', $result);
        $this->assertSame('2026-07-02', $result['date']);
        $this->assertCount(24, $result['previsions']);
        $this->assertSame('00h', $result['previsions'][0]['heure']);
        $this->assertSame('Éclaircies', $result['previsions'][0]['condition']);
    }

    public function testMomentFiltersHourRange(): void
    {
        $result = $this->tool()(null, 'matin', null);

        $this->assertSame(['06h', '07h', '08h', '09h', '10h'], array_column($result['previsions'], 'heure'));
    }

    public function testPreciseHourWinsOverMoment(): void
    {
        $result = $this->tool()(null, 'matin', 15);

        $this->assertCount(1, $result['previsions']);
        $this->assertSame('15h', $result['previsions'][0]['heure']);
    }

    public function testTomorrowIsAvailable(): void
    {
        $result = $this->tool()('2026-07-03', 'midi', null);

        $this->assertSame('2026-07-03', $result['date']);
        $this->assertSame(['11h', '12h', '13h'], array_column($result['previsions'], 'heure'));
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
                    20.5,
                    1013.2,
                    60,
                    0.0,
                    7.4,
                    135,
                    1,
                );
            }
        }

        $provider = $this->createStub(WeatherForecastProvider::class);
        $provider->method('get')->willReturn($forecasts);

        return new WeatherForecastTool($provider);
    }
}

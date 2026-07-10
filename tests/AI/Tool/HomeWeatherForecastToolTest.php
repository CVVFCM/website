<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\HomeWeatherForecastTool;
use App\DTO\WeatherForecast;
use App\ML\WeatherModelInference;
use App\Weather\WeatherForecastProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

final class HomeWeatherForecastToolTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../ML/Fixtures';

    #[\Override]
    protected function setUp(): void
    {
        Clock::set(new MockClock(new \DateTimeImmutable('2026-07-10 09:00:00', new \DateTimeZone('Europe/Paris'))));
    }

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testItReturnsCorrectedWindForTodayAndTomorrow(): void
    {
        $forecasts = [];
        foreach (['2026-07-10', '2026-07-11'] as $date) {
            foreach ([10, 13, 16] as $hour) {
                $forecasts[] = $this->forecast(sprintf('%s %02d:00:00', $date, $hour));
            }
        }
        $provider = $this->createStub(WeatherForecastProvider::class);
        $provider->method('get')->willReturn($forecasts);

        $result = (new HomeWeatherForecastTool($provider, $this->inference(self::FIXTURES.'/model_weights.json')))();

        $this->assertArrayHasKey('aujourd_hui', $result);
        $this->assertArrayHasKey('demain', $result);
        $this->assertCount(3, $result['aujourd_hui']);
        $this->assertSame(['Matin', 'Midi', 'Après-midi'], array_column($result['aujourd_hui'], 'moment'));

        $morning = $result['aujourd_hui'][0];
        $this->assertIsInt($morning['vent_noeuds']);
        $this->assertGreaterThanOrEqual(0, $morning['vent_noeuds']);
        $this->assertIsString($morning['direction']);
        $this->assertNotSame('', $morning['direction']);
    }

    public function testItSkipsNonKeyHours(): void
    {
        // 08:00 is not a key moment → ignored.
        $provider = $this->createStub(WeatherForecastProvider::class);
        $provider->method('get')->willReturn([
            $this->forecast('2026-07-10 08:00:00'),
            $this->forecast('2026-07-10 13:00:00'),
        ]);

        $result = (new HomeWeatherForecastTool($provider, $this->inference(self::FIXTURES.'/model_weights.json')))();

        $this->assertArrayHasKey('aujourd_hui', $result);
        $this->assertCount(1, $result['aujourd_hui']);
        $this->assertSame('Midi', $result['aujourd_hui'][0]['moment']);
    }

    public function testItReportsWhenTheModelIsUnavailable(): void
    {
        $provider = $this->createStub(WeatherForecastProvider::class);
        $provider->method('get')->willReturn([$this->forecast('2026-07-10 13:00:00')]);

        $result = (new HomeWeatherForecastTool($provider, $this->inference(self::FIXTURES.'/does-not-exist.json')))();

        $this->assertSame(['erreur' => 'La météo maison est indisponible pour le moment.'], $result);
    }

    private function inference(string $weightsPath): WeatherModelInference
    {
        return new WeatherModelInference($weightsPath, self::FIXTURES.'/scaler_params.json');
    }

    private function forecast(string $datetime): WeatherForecast
    {
        return new WeatherForecast(
            new \DateTimeImmutable($datetime, new \DateTimeZone('Europe/Paris')),
            20.0,   // temperature
            1013.0, // pressure
            70,     // humidity
            0.0,    // precipitation
            12.0,   // windSpeed
            200,    // windDirection
            1,      // weatherCode
        );
    }
}

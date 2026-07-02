<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\DTO\WeatherForecast;
use App\Weather\WeatherForecastProvider;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

use function Symfony\Component\Clock\now;

/**
 * Hourly weather forecast at the lake — today and tomorrow only (48h provider window).
 */
#[AsTool('weather_forecast', 'Prévisions météo horaires au lac des Vieilles Forges, pour aujourd\'hui et demain uniquement (température, condition, vent en nœuds, direction, précipitations). Filtrable par date, moment de la journée ou heure précise.')]
final readonly class WeatherForecastTool
{
    private const array MOMENTS = [
        'matin' => [6, 11],
        'midi' => [11, 14],
        'apres-midi' => [14, 18],
        'soiree' => [18, 22],
    ];

    public function __construct(
        private WeatherForecastProvider $weatherForecastProvider,
    ) {
    }

    /**
     * @return array{erreur: string}|array{date: string, previsions: list<array<string, float|int|string>>}
     */
    public function __invoke(
        #[Schema(description: 'Date des prévisions (aujourd\'hui ou demain uniquement). Par défaut : aujourd\'hui.', pattern: '^\d{4}-\d{2}-\d{2}$')]
        ?string $date = null,
        #[Schema(description: 'Moment de la journée.', enum: ['matin', 'midi', 'apres-midi', 'soiree'])]
        ?string $moment = null,
        #[Schema(description: 'Heure précise (0-23). Prioritaire sur le moment.', minimum: 0, maximum: 23)]
        ?int $heure = null,
    ): array {
        $today = now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
        $tomorrow = now()->setTimezone(new \DateTimeZone('Europe/Paris'))->modify('+1 day')->format('Y-m-d');
        $date ??= $today;

        if (!\in_array($date, [$today, $tomorrow], true)) {
            return ['erreur' => "Prévisions disponibles uniquement pour aujourd'hui et demain."];
        }

        $forecasts = array_values(array_filter(
            $this->weatherForecastProvider->get(),
            fn (WeatherForecast $forecast): bool => $this->matches($forecast, $date, $moment, $heure),
        ));

        if ([] === $forecasts) {
            return ['erreur' => 'Prévisions indisponibles pour le moment.'];
        }

        return [
            'date' => $date,
            'previsions' => array_map(
                static fn (WeatherForecast $forecast): array => [
                    'heure' => $forecast->date->format('H\h'),
                    'temperature' => round($forecast->temperature, 1),
                    'condition' => $forecast->getConditionLabel(),
                    'vent_noeuds' => (int) round($forecast->windSpeed),
                    'direction_vent' => $forecast->getWindCardinalDirection()->value,
                    'precipitation_mm' => $forecast->precipitation,
                    'humidite_pourcent' => $forecast->humidity,
                    'pression_hpa' => round($forecast->pressure, 1),
                ],
                $forecasts,
            ),
        ];
    }

    private function matches(WeatherForecast $forecast, string $date, ?string $moment, ?int $heure): bool
    {
        if ($forecast->date->format('Y-m-d') !== $date) {
            return false;
        }

        $hour = (int) $forecast->date->format('G');

        if (null !== $heure) {
            return $hour === $heure;
        }

        if (null !== $moment && isset(self::MOMENTS[$moment])) {
            [$from, $to] = self::MOMENTS[$moment];

            return $hour >= $from && $hour < $to;
        }

        return true;
    }
}

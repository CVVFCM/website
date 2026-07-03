<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\DTO\WeatherForecast;
use App\Weather\WeatherForecastProvider;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

use function Symfony\Component\Clock\now;

/**
 * Weather forecast at the lake — today and tomorrow only (48h provider window).
 * Returns per-moment abstracts (matin, midi, après-midi, soirée); the `heure`
 * parameter switches to the full hourly detail of that single hour.
 */
#[AsTool('weather_forecast', 'Prévisions météo au lac des Vieilles Forges, pour aujourd\'hui et demain uniquement. Par défaut : résumé par moment de la journée (matin, midi, après-midi, soirée) avec températures, condition, vent en nœuds et précipitations. Le paramètre heure donne le détail complet d\'une heure précise.')]
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
     * @return array{erreur: string}|array{date: string, previsions: list<array<string, float|int|string>>}|array{date: string, moments: list<array<string, float|int|string>>}
     */
    public function __invoke(
        #[Schema(description: 'Date des prévisions (aujourd\'hui ou demain uniquement). Par défaut : aujourd\'hui.', pattern: '^\d{4}-\d{2}-\d{2}$')]
        ?string $date = null,
        #[Schema(description: 'Moment de la journée.', enum: ['matin', 'midi', 'apres-midi', 'soiree'])]
        ?string $moment = null,
        #[Schema(description: 'Heure précise (0-23), pour le détail horaire complet. Prioritaire sur le moment.', minimum: 0, maximum: 23)]
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
            static fn (WeatherForecast $forecast): bool => $forecast->date->format('Y-m-d') === $date,
        ));

        if ([] === $forecasts) {
            return ['erreur' => 'Prévisions indisponibles pour le moment.'];
        }

        if (null !== $heure) {
            $hourly = array_values(array_filter(
                $forecasts,
                static fn (WeatherForecast $forecast): bool => (int) $forecast->date->format('G') === $heure,
            ));

            if ([] === $hourly) {
                return ['erreur' => 'Prévisions indisponibles pour le moment.'];
            }

            return ['date' => $date, 'previsions' => array_map(self::hourlyDetail(...), $hourly)];
        }

        $moments = [];
        foreach (self::MOMENTS as $name => [$from, $to]) {
            if (null !== $moment && $name !== $moment) {
                continue;
            }

            $slice = array_values(array_filter(
                $forecasts,
                static fn (WeatherForecast $forecast): bool => (int) $forecast->date->format('G') >= $from
                    && (int) $forecast->date->format('G') < $to,
            ));

            if ([] !== $slice) {
                $moments[] = self::abstract($name, $from, $to, $slice);
            }
        }

        if ([] === $moments) {
            return ['erreur' => 'Prévisions indisponibles pour le moment.'];
        }

        return ['date' => $date, 'moments' => $moments];
    }

    /**
     * @param non-empty-list<WeatherForecast> $forecasts
     *
     * @return array<string, float|int|string>
     */
    private static function abstract(string $name, int $from, int $to, array $forecasts): array
    {
        $temperatures = array_map(static fn (WeatherForecast $forecast): float => $forecast->temperature, $forecasts);
        $windSpeeds = array_map(static fn (WeatherForecast $forecast): float => $forecast->windSpeed, $forecasts);
        $precipitations = array_map(static fn (WeatherForecast $forecast): float => $forecast->precipitation, $forecasts);
        $humidities = array_map(static fn (WeatherForecast $forecast): int => $forecast->humidity, $forecasts);

        return [
            'moment' => $name,
            'heures' => sprintf('%02dh-%02dh', $from, $to),
            'temperature_min' => round(min($temperatures), 1),
            'temperature_max' => round(max($temperatures), 1),
            'condition' => self::dominantCondition($forecasts),
            'vent_moyen_noeuds' => (int) round(array_sum($windSpeeds) / (float) \count($windSpeeds)),
            'vent_max_noeuds' => (int) round(max($windSpeeds)),
            'direction_vent' => self::dominantDirection($forecasts),
            'precipitation_totale_mm' => round(array_sum($precipitations), 1),
            'humidite_moyenne_pourcent' => (int) round(array_sum($humidities) / \count($humidities)),
        ];
    }

    /**
     * Most frequent condition label over the slice; ties go to the first seen.
     *
     * @param non-empty-list<WeatherForecast> $forecasts
     */
    private static function dominantCondition(array $forecasts): string
    {
        $counts = [];
        foreach ($forecasts as $forecast) {
            $label = $forecast->getConditionLabel();
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return self::mostFrequent($counts);
    }

    /**
     * @param non-empty-list<WeatherForecast> $forecasts
     */
    private static function dominantDirection(array $forecasts): string
    {
        $counts = [];
        foreach ($forecasts as $forecast) {
            $direction = $forecast->getWindCardinalDirection()->value;
            $counts[$direction] = ($counts[$direction] ?? 0) + 1;
        }

        return self::mostFrequent($counts);
    }

    /**
     * @param non-empty-array<string, int> $counts
     */
    private static function mostFrequent(array $counts): string
    {
        $best = '';
        $bestCount = 0;
        foreach ($counts as $value => $count) {
            if ($count > $bestCount) {
                $best = $value;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * @return array<string, float|int|string>
     */
    private static function hourlyDetail(WeatherForecast $forecast): array
    {
        return [
            'heure' => $forecast->date->format('H\h'),
            'temperature' => round($forecast->temperature, 1),
            'condition' => $forecast->getConditionLabel(),
            'vent_noeuds' => (int) round($forecast->windSpeed),
            'direction_vent' => $forecast->getWindCardinalDirection()->value,
            'precipitation_mm' => $forecast->precipitation,
            'humidite_pourcent' => $forecast->humidity,
            'pression_hpa' => round($forecast->pressure, 1),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\DTO\WeatherForecast;
use App\Entity\WeatherForecastRecord;
use App\ML\ForecastFeatureExtractor;
use App\ML\WeatherModelInference;
use App\Weather\CardinalDirection;
use App\Weather\WeatherForecastProvider;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

use function Symfony\Component\Clock\now;

/**
 * "Météo maison" — the club's own wind forecast: the raw open-meteo forecast corrected by the
 * in-house ML model, for today and tomorrow at the key moments (matin/midi/après-midi).
 *
 * Trigger is phrase-gated in the prompt: only used when the visitor says « météo maison ».
 */
#[AsTool('home_weather', 'La « météo maison » : la prévision de vent du club, corrigée par notre modèle maison, pour aujourd\'hui et demain (matin, midi, après-midi), vent en nœuds. À n\'utiliser QUE si le visiteur emploie l\'expression « météo maison ».')]
final readonly class HomeWeatherForecastTool
{
    public function __construct(
        private WeatherForecastProvider $forecastProvider,
        private WeatherModelInference $inference,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $days = [
            'aujourd_hui' => now()->setTimezone($paris)->format('Y-m-d'),
            'demain' => now()->setTimezone($paris)->modify('+1 day')->format('Y-m-d'),
        ];

        $forecasts = $this->forecastProvider->get();

        $result = [];
        foreach ($days as $key => $date) {
            $moments = [];
            foreach ($forecasts as $forecast) {
                if ($forecast->date->format('Y-m-d') !== $date) {
                    continue;
                }
                // Key moments only (matin/midi/après-midi), mirroring WeatherForecast::HOURS.
                if (!\array_key_exists($forecast->date->format('H'), WeatherForecast::HOURS)) {
                    continue;
                }

                $prediction = $this->inference->tryPredict(
                    ForecastFeatureExtractor::fromRecord(WeatherForecastRecord::fromWeatherForecast($forecast)),
                );
                if (null === $prediction) {
                    return ['erreur' => 'La météo maison est indisponible pour le moment.'];
                }

                $moments[] = [
                    'moment' => $forecast->getLabel(),
                    'vent_noeuds' => (int) round($prediction->windSpeed),
                    'direction' => CardinalDirection::fromDirection($prediction->windDirection)->value,
                ];
            }

            if ([] !== $moments) {
                $result[$key] = $moments;
            }
        }

        if ([] === $result) {
            return ['erreur' => 'La météo maison est indisponible pour le moment.'];
        }

        return $result;
    }
}

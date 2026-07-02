<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Weather\LiveWeatherProvider;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Real-time conditions from the club's weather station at the lake.
 */
#[AsTool('live_weather', 'Météo en direct de la station du club au lac des Vieilles Forges : température, humidité, pression, pluie, radiation solaire et vent (vitesse, rafales, direction, moyennes) en nœuds.')]
final readonly class LiveWeatherTool
{
    public function __construct(
        private LiveWeatherProvider $liveWeatherProvider,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $weather = $this->liveWeatherProvider->get();

        if (null === $weather) {
            return ['erreur' => 'Station météo indisponible pour le moment.'];
        }

        return [
            'mis_a_jour' => $weather->updatedAt->format('Y-m-d H:i:s'),
            'temperature' => [
                'actuelle_celsius' => round($weather->temperature, 1),
                'min_jour' => round($weather->temperatureMin, 1),
                'max_jour' => round($weather->temperatureMax, 1),
            ],
            'humidite' => [
                'actuelle_pourcent' => round($weather->humidity * 100.0),
                'min_jour' => round($weather->humidityMin * 100.0),
                'max_jour' => round($weather->humidityMax * 100.0),
            ],
            'pression' => [
                'actuelle_hpa' => round($weather->pressure, 1),
                'min_jour' => round($weather->pressureMin, 1),
                'max_jour' => round($weather->pressureMax, 1),
            ],
            'pluie' => [
                'rythme_mm_h' => $weather->rainRate,
                'total_jour_mm' => $weather->rainTotal,
            ],
            'radiation_solaire_w_m2' => $weather->solarRadiation,
            'vent' => [
                'vitesse_noeuds' => (int) round($weather->windSpeed),
                'rafales_noeuds' => (int) round($weather->windGusts),
                'direction' => $weather->getWindDirectionAsCardinal()->value,
                'vitesse_moyenne_noeuds' => (int) round($weather->windSpeedAverage),
                'direction_moyenne' => $weather->getWindDirectionAverageAsCardinal()->value,
                'min_jour_noeuds' => (int) round($weather->windSpeedMin),
                'max_jour_noeuds' => (int) round($weather->windSpeedMax),
            ],
            'lien_station' => $this->liveWeatherProvider->getExternalLink(),
        ];
    }
}

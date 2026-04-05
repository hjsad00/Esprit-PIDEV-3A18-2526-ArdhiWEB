<?php

namespace App\Service\UserAndDiag;

class WeatherAlertService
{
    private WeatherService $weatherService;

    public function __construct(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }

    /**
     * Analyzes the weather forecast for a location and returns disease risk alerts.
     */
    public function getDiseaseRiskAlerts(float $lat, float $lon, int $hours = 72): array
    {
        $alerts = [];
        try {
            $current = $this->weatherService->getCurrentWeather($lat, $lon);
            if (!$current)
                return $alerts;

            $forecast = $this->weatherService->getForecast($lat, $lon);

            // 1. Check current conditions
            if ($current['humidity'] >= 80 && $current['temperature'] >= 18) {
                $alerts[] = [
                    'riskLevel' => 'Élevé',
                    'diseaseType' => 'Maladies fongiques',
                    'reason' => sprintf("Humidité élevée (%d%%) + températures chaudes (%.1f°C) — conditions idéales pour les infections fongiques.", $current['humidity'], $current['temperature']),
                    'advice' => "Inspectez vos cultures pour des signes de mildiou ou oïdium. Envisagez un traitement fongicide préventif.",
                    'icon' => '🔴'
                ];
            } elseif ($current['humidity'] >= 70 && $current['temperature'] >= 15) {
                $alerts[] = [
                    'riskLevel' => 'Modéré',
                    'diseaseType' => 'Maladies fongiques',
                    'reason' => sprintf("Humidité modérée (%d%%) + températures douces (%.1f°C) — risque modéré de maladies fongiques.", $current['humidity'], $current['temperature']),
                    'advice' => "Surveillez vos cultures et assurez une bonne aération entre les plants.",
                    'icon' => '🟡'
                ];
            }

            if ($current['temperature'] >= 30 && $current['humidity'] < 40) {
                $alerts[] = [
                    'riskLevel' => 'Modéré',
                    'diseaseType' => 'Stress hydrique & Acariens',
                    'reason' => sprintf("Chaleur élevée (%.1f°C) + faible humidité (%d%%) — risque de stress hydrique et prolifération d'acariens.", $current['temperature'], $current['humidity']),
                    'advice' => "Arrosez en soirée. Inspectez le dessous des feuilles.",
                    'icon' => '🟡'
                ];
            }

            // 2. Check forecast for upcoming risks
            if ($forecast && isset($forecast['hourly'])) {
                $hourly = $forecast['hourly'];
                $limit = min($hours, count($hourly['time']));

                $maxHumidityRun = 0;
                $currentRun = 0;
                for ($i = 0; $i < $limit; $i++) {
                    if ($hourly['relative_humidity_2m'][$i] >= 80 && $hourly['temperature_2m'][$i] >= 15) {
                        $currentRun++;
                        $maxHumidityRun = max($maxHumidityRun, $currentRun);
                    } else {
                        $currentRun = 0;
                    }
                }

                if ($maxHumidityRun >= 12 && !$this->hasType($alerts, 'fongique', 'Élevé')) {
                    $alerts[] = [
                        'riskLevel' => 'Élevé',
                        'diseaseType' => 'Prévision: Maladies fongiques',
                        'reason' => sprintf("Les prévisions indiquent %dh consécutives de forte humidité (>80%%) dans les %d prochaines heures.", $maxHumidityRun, $hours),
                        'advice' => "Envisagez un traitement fongicide préventif avant cette période humide.",
                        'icon' => '🔴'
                    ];
                } elseif ($maxHumidityRun >= 6 && !$this->hasType($alerts, 'fongique')) {
                    $alerts[] = [
                        'riskLevel' => 'Modéré',
                        'diseaseType' => 'Prévision: Risque fongique',
                        'reason' => sprintf("Les prévisions indiquent %dh de forte humidité dans les %d prochaines heures.", $maxHumidityRun, $hours),
                        'advice' => "Assurez une bonne circulation d'air et surveillez vos cultures.",
                        'icon' => '🟡'
                    ];
                }

                // Check frost
                $frost = false;
                for ($i = 0; $i < $limit; $i++) {
                    if ($hourly['temperature_2m'][$i] <= 2) {
                        $frost = true;
                        break;
                    }
                }
                if ($frost) {
                    $alerts[] = [
                        'riskLevel' => 'Élevé',
                        'diseaseType' => 'Risque de gel',
                        'reason' => sprintf("Des températures proches de 0°C sont prévues dans les %d prochaines heures.", $hours),
                        'advice' => "Protégez les cultures sensibles avec des voiles de protection.",
                        'icon' => '🔴'
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log error
        }

        return $alerts;
    }

    private function hasType(array $alerts, string $keyword, ?string $level = null): bool
    {
        foreach ($alerts as $a) {
            if (str_contains(strtolower($a['diseaseType']), $keyword)) {
                if ($level === null || $a['riskLevel'] === $level) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getTreatmentTiming(float $lat, float $lon): array
    {
        $forecast = $this->weatherService->getForecast($lat, $lon);
        if (!$forecast) {
            return ['overallAdvice' => 'Impossible de récupérer les prévisions météo.'];
        }

        $rainWarning = $this->weatherService->getRainWarning($forecast);
        $sprayWindow = $this->weatherService->getSprayWindow($forecast);

        return [
            'sprayWindow' => $sprayWindow,
            'rainWarning' => $rainWarning,
            'overallAdvice' => $rainWarning ?: $sprayWindow
        ];
    }
}

<?php

namespace App\Service\ExternalAPI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class WeatherService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Récupère les données météo depuis Open-Meteo
     */
    public function getWeatherData(float $latitude, float $longitude): array
    {
        try {
            $response = $this->httpClient->request('GET', 
                'https://api.open-meteo.com/v1/forecast',
                [
                    'query' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'current' => 'temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m',
                        'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
                        'forecast_days' => 7,
                        'timezone' => 'auto',
                    ],
                ]
            );

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Open-Meteo API error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Analyse les données météo et retourne des alertes
     */
    public function analyzeWeather(array $weatherData): array
    {
        $alerts = [];

        if (isset($weatherData['current'])) {
            $current = $weatherData['current'];
            
            // Alerte gel
            if ($current['temperature_2m'] < 3) {
                $alerts['frost'] = [
                    'level' => 'danger',
                    'message' => 'Alerte gel : Température ' . $current['temperature_2m'] . '°C',
                ];
            }

            // Alerte pluie excessive
            if ($current['precipitation'] > 30) {
                $alerts['heavy_rain'] = [
                    'level' => 'warning',
                    'message' => 'Alerte pluie : ' . $current['precipitation'] . 'mm',
                ];
            }
        }

        if (isset($weatherData['daily'])) {
            $dailyData = $weatherData['daily'];
            $maxTemps = $dailyData['temperature_2m_max'];
            $minTemps = $dailyData['temperature_2m_min'];
            $precipitations = $dailyData['precipitation_sum'];

            // Compter les jours de canicule (>35°C)
            $heatDays = count(array_filter($maxTemps, fn($t) => $t > 35));
            if ($heatDays > 0) {
                $alerts['heat_stress'] = [
                    'level' => 'warning',
                    'message' => "{$heatDays} jours de température > 35°C détectés",
                ];
            }

            // Compter les jours de gel
            $frostDays = count(array_filter($minTemps, fn($t) => $t < 0));
            if ($frostDays > 0) {
                $alerts['frost_days'] = [
                    'level' => 'warning',
                    'message' => "{$frostDays} jours de température < 0°C prévus",
                ];
            }
        }

        return $alerts;
    }

    /**
     * Calcule les facteurs climatiques pour le calcul ROI
     */
    public function getClimateFactors(array $weatherData): array
    {
        $factors = [
            'frost_days' => 0,
            'heat_days' => 0,
            'heavy_rain_days' => 0,
        ];

        if (isset($weatherData['daily'])) {
            $dailyData = $weatherData['daily'];
            $factors['frost_days'] = count(array_filter($dailyData['temperature_2m_min'] ?? [], fn($t) => $t < 0));
            $factors['heat_days'] = count(array_filter($dailyData['temperature_2m_max'] ?? [], fn($t) => $t > 35));
            $factors['heavy_rain_days'] = count(array_filter($dailyData['precipitation_sum'] ?? [], fn($p) => $p > 30));
        }

        return $factors;
    }
}

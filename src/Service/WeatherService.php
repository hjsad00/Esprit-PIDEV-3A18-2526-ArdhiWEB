<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $openWeatherApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $openWeatherApiKey;
    }

    /**
     * Récupère météo actuelle pour une localisation
     */
    public function getCurrentWeather(float $latitude, float $longitude): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                'query' => [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang' => 'fr'
                ]
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'temperature' => round($data['main']['temp'], 1),
                'humidity' => $data['main']['humidity'],
                'pressure' => $data['main']['pressure'],
                'rain' => $data['rain']['1h'] ?? 0,
                'wind_speed' => $data['wind']['speed'],
                'description' => $data['weather'][0]['description'],
                'icon' => $data['weather'][0]['icon'],
                'clouds' => $data['clouds']['all'],
                'feels_like' => round($data['main']['feels_like'], 1)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Impossible de récupérer la météo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère prévisions 5 jours
     */
    public function getForecast(float $latitude, float $longitude): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/forecast', [
                'query' => [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang' => 'fr'
                ]
            ]);

            $data = $response->toArray();
            $forecast = [];

            // Récupérer prévisions par jour (1 par jour à 12h)
            for ($i = 0; $i < count($data['list']); $i += 8) {
                $item = $data['list'][$i];
                $forecast[] = [
                    'date' => date('d/m', $item['dt']),
                    'temp' => round($item['main']['temp'], 1),
                    'humidity' => $item['main']['humidity'],
                    'rain_prob' => round($item['pop'] * 100),
                    'description' => $item['weather'][0]['description'],
                    'wind_speed' => $item['wind']['speed']
                ];
            }

            return [
                'success' => true,
                'forecast' => $forecast
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Impossible de récupérer les prévisions'
            ];
        }
    }

    /**
     * Analyse conditions climatiques pour cultures
     */
    public function analyzeWeatherForCulture(array $weather, string $season): array
    {
        $temperature = $weather['temperature'] ?? 25;
        $humidity = $weather['humidity'] ?? 50;
        $rain = $weather['rain'] ?? 0;

        $analysis = [
            'temperature' => $temperature,
            'humidity' => $humidity,
            'rain' => $rain,
            'season' => $season,
            'is_hot' => $temperature > 30,
            'is_cold' => $temperature < 10,
            'is_dry' => $humidity < 40,
            'is_wet' => $humidity > 75,
            'warnings' => []
        ];

        // Ajouter des avertissements
        if ($temperature > 35) {
            $analysis['warnings'][] = '🔥 Chaleur extrême détectée';
        }
        if ($humidity > 80) {
            $analysis['warnings'][] = '💧 Humidité très élevée (risque maladies fongiques)';
        }
        if ($humidity < 30) {
            $analysis['warnings'][] = '⚠️ Air très sec (risque de stress hydrique)';
        }

        return $analysis;
    }
}

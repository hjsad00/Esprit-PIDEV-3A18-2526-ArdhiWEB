<?php

namespace App\Service\UserAndDiag;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class WeatherService
{
    private const DEFAULT_LAT = 36.8065;
    private const DEFAULT_LON = 10.1815;

    private HttpClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Get current weather for a specific location using MET Norway API.
     */
    public function getCurrentWeather(float $lat = self::DEFAULT_LAT, float $lon = self::DEFAULT_LON): ?array
    {
        try {
            // MET Norway requires a specific User-Agent and recommends "compact" for mobile/web
            $url = sprintf(
                "https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=%.4f&lon=%.4f",
                $lat,
                $lon
            );

            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'ArdhiApp/1.0 (contact@ardhi.com)'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning("MET Norway API returned status " . $response->getStatusCode());
                return null;
            }

            try {
                $json = $response->toArray();
            } catch (\Exception $e) {
                $this->logger->error("MET Norway JSON decode error: " . $e->getMessage());
                return null;
            }

            $timeseries = $json['properties']['timeseries'] ?? null;
            if (!$timeseries || count($timeseries) === 0)
                return null;

            $currentData = $timeseries[0]['data'];
            $instant = $currentData['instant']['details'];
            $next1h = $currentData['next_1_hours'] ?? $currentData['next_6_hours'] ?? null;

            $data = [
                'temperature' => $instant['air_temperature'],
                'humidity' => $instant['relative_humidity'],
                'precipitation' => $next1h['details']['precipitation_amount'] ?? 0,
                'symbolCode' => $next1h['summary']['symbol_code'] ?? 'clearsky_day',
                'windSpeed' => $instant['wind_speed'] * 3.6, // m/s to km/h
                'windDirection' => $instant['wind_from_direction'],
                'apparentTemperature' => $this->calculateApparentTemperature($instant['air_temperature'], $instant['wind_speed']),
                'latitude' => $lat,
                'longitude' => $lon
            ];

            $data['icon'] = $this->getMetIcon($data['symbolCode']);
            $data['condition'] = $this->getMetDescription($data['symbolCode']);
            $data['advice'] = $this->generateAgriculturalAdvice($data);
            $data['windDirectionString'] = $this->getWindDirectionString($data['windDirection']);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error("WeatherService MET Norway error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get 72-hour forecast (hourly) using MET Norway API.
     */
    public function getForecast(float $lat = self::DEFAULT_LAT, float $lon = self::DEFAULT_LON): ?array
    {
        try {
            $url = sprintf(
                "https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=%.4f&lon=%.4f",
                $lat,
                $lon
            );

            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'ArdhiApp/1.0 (contact@ardhi.com)'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $json = $response->toArray();
            $timeseries = $json['properties']['timeseries'] ?? [];

            // Format to match expected structure
            $formatted = [
                'hourly' => [
                    'time' => [],
                    'temperature_2m' => [],
                    'relative_humidity_2m' => [],
                    'precipitation' => [],
                    'weather_code' => []
                ]
            ];

            // Take first 72 entries (approx 72 hours)
            foreach (array_slice($timeseries, 0, 72) as $entry) {
                $formatted['hourly']['time'][] = $entry['time'];
                $formatted['hourly']['temperature_2m'][] = $entry['data']['instant']['details']['air_temperature'];
                $formatted['hourly']['relative_humidity_2m'][] = $entry['data']['instant']['details']['relative_humidity'];
                $next1h = $entry['data']['next_1_hours'] ?? $entry['data']['next_6_hours'] ?? null;
                $formatted['hourly']['precipitation'][] = $next1h['details']['precipitation_amount'] ?? 0;
                $formatted['hourly']['weather_code'][] = 0; // MET symbol codes handled separately if needed
            }

            return $formatted;
        } catch (\Exception $e) {
            $this->logger->error("Forecast MET Norway error: " . $e->getMessage());
            return null;
        }
    }

    private function calculateApparentTemperature(float $temp, float $windSpeedMs): float
    {
        // Simple approximation
        return $temp - ($windSpeedMs * 0.7);
    }

    private function getWindDirectionString(float $direction): string
    {
        $dirs = ["N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S", "SSW", "SW", "WSW", "W", "WNW", "NW", "NNW"];
        return $dirs[(int) round((($direction % 360) / 22.5)) % 16];
    }

    private function getMetIcon(string $symbol): string
    {
        $symbol = explode('_', $symbol)[0];
        return match ($symbol) {
            'clearsky' => "☀️",
            'fair', 'partlycloudy' => "⛅",
            'cloudy' => "☁️",
            'rain', 'heavyrain', 'lightrain' => "🌧️",
            'snow', 'heavysnow', 'lightsnow' => "❄️",
            'sleet' => "🌨️",
            'thunderstorm' => "⛈️",
            'fog' => "🌫️",
            default => "🌡️",
        };
    }

    private function getMetDescription(string $symbol): string
    {
        // Remove day/night suffix
        $symbol = explode('_', $symbol)[0];
        return match ($symbol) {
            'clearsky' => "Ciel dégagé",
            'fair' => "Beau temps",
            'partlycloudy' => "Partiellement nuageux",
            'cloudy' => "Nuageux",
            'rain' => "Pluie",
            'heavyrain' => "Pluie forte",
            'lightrain' => "Pluie légère",
            'snow' => "Neige",
            'sleet' => "Neige fondante",
            'thunderstorm' => "Orage",
            'fog' => "Brouillard",
            default => "Conditions variables",
        };
    }

    private function generateAgriculturalAdvice(array $data): string
    {
        // Adjusted for MET Norway data structure
        if ($data['humidity'] > 85 && $data['temperature'] > 18) {
            return "⚠️ Alerte Humidité: Risque élevé de maladies fongiques (Mildiou, Oïdium). Évitez l'irrigation par aspersion.";
        }
        if ($data['precipitation'] > 2) {
            return "🌧️ Pluie prévue: Suspendez les traitements phytosanitaires et l'irrigation.";
        }
        if ($data['temperature'] > 35) {
            return "🔥 Canicule: Assurez une irrigation suffisante en début ou fin de journée pour éviter le stress hydrique.";
        }
        if ($data['temperature'] < 4) {
            return "❄️ Risque de gel: Protégez les cultures sensibles et les semis.";
        }
        if (str_contains($data['symbolCode'] ?? '', 'thunder')) {
            return "⛈️ Orages: Évitez les travaux aux champs et mettez le matériel à l'abri.";
        }
        return "✅ Conditions favorables: Bon moment pour les inspections de routine et l'entretien.";
    }

    public function getSprayWindow(array $forecast, int $minHours = 4): string
    {
        $hourly = $forecast['hourly'] ?? null;
        if (!$hourly)
            return "Données indisponibles";

        $consecutiveDry = 0;
        $windowStart = null;

        $times = $hourly['time'];
        $precips = $hourly['precipitation'];

        for ($i = 0; $i < count($times); $i++) {
            if ($precips[$i] <= 0.1) {
                if ($consecutiveDry == 0) {
                    $windowStart = $times[$i];
                }
                $consecutiveDry++;
                if ($consecutiveDry >= $minHours) {
                    $displayTime = $this->formatTime($windowStart);
                    return "✅ Fenêtre de traitement: " . $displayTime . " (" . $consecutiveDry . "h+ sans pluie prévue)";
                }
            } else {
                $consecutiveDry = 0;
                $windowStart = null;
            }
        }

        return "⚠️ Pas de fenêtre sèche de " . $minHours . "h dans les 72 prochaines heures. Reportez le traitement.";
    }

    public function getRainWarning(array $forecast, int $hoursAhead = 6): ?string
    {
        $hourly = $forecast['hourly']['precipitation'] ?? [];
        for ($i = 0; $i < min($hoursAhead, count($hourly)); $i++) {
            if ($hourly[$i] > 0.5) {
                return "🌧️ Pluie prévue dans " . ($i + 1) . "h — retardez le traitement.";
            }
        }
        return null;
    }

    private function formatTime(string $isoTime): string
    {
        try {
            $dt = new \DateTime($isoTime);
            return $dt->format('d/m à H\hi');
        } catch (\Exception $e) {
            return $isoTime;
        }
    }
}

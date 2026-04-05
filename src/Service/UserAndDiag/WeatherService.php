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
     * Get current weather for a specific location.
     */
    public function getCurrentWeather(float $lat = self::DEFAULT_LAT, float $lon = self::DEFAULT_LON): ?array
    {
        try {
            $url = sprintf(
                "https://api.open-meteo.com/v1/forecast?latitude=%.4f&longitude=%.4f&current=temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m,wind_direction_10m,apparent_temperature&timezone=auto",
                $lat,
                $lon
            );

            $response = $this->client->request('GET', $url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $json = $response->toArray();
            $current = $json['current'] ?? null;
            if (!$current)
                return null;

            $data = [
                'temperature' => $current['temperature_2m'],
                'humidity' => $current['relative_humidity_2m'],
                'precipitation' => $current['precipitation'],
                'weatherCode' => $current['weather_code'],
                'windSpeed' => $current['wind_speed_10m'],
                'windDirection' => $current['wind_direction_10m'],
                'apparentTemperature' => $current['apparent_temperature'],
                'latitude' => $lat,
                'longitude' => $lon
            ];

            $data['icon'] = $this->getWeatherIcon($data['weatherCode']);
            $data['condition'] = $this->getWeatherDescription($data['weatherCode']);
            $data['advice'] = $this->generateAgriculturalAdvice($data);
            $data['windDirectionString'] = $this->getWindDirectionString($data['windDirection']);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error("WeatherService error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get 72-hour forecast (hourly).
     */
    public function getForecast(float $lat = self::DEFAULT_LAT, float $lon = self::DEFAULT_LON): ?array
    {
        try {
            $url = sprintf(
                "https://api.open-meteo.com/v1/forecast?latitude=%.4f&longitude=%.4f&hourly=temperature_2m,relative_humidity_2m,precipitation,weather_code&forecast_days=3&timezone=auto",
                $lat,
                $lon
            );

            $response = $this->client->request('GET', $url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error("Forecast error: " . $e->getMessage());
            return null;
        }
    }

    private function getWindDirectionString(float $direction): string
    {
        $dirs = ["N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S", "SSW", "SW", "WSW", "W", "WNW", "NW", "NNW"];
        return $dirs[(int) round((($direction % 360) / 22.5)) % 16];
    }

    private function getWeatherIcon(int $code): string
    {
        if ($code == 0)
            return "☀️";
        if ($code >= 1 && $code <= 3)
            return "⛅";
        if ($code >= 45 && $code <= 48)
            return "🌫️";
        if ($code >= 51 && $code <= 55)
            return "🌦️";
        if ($code >= 61 && $code <= 65)
            return "🌧️";
        if ($code >= 71 && $code <= 77)
            return "❄️";
        if ($code >= 80 && $code <= 82)
            return "🌦️";
        if ($code >= 95 && $code <= 99)
            return "⛈️";
        return "🌡️";
    }

    private function getWeatherDescription(int $code): string
    {
        if ($code == 0)
            return "Ciel dégagé";
        if ($code >= 1 && $code <= 3)
            return "Partiellement nuageux";
        if ($code >= 45 && $code <= 48)
            return "Brouillard";
        if ($code >= 51 && $code <= 55)
            return "Bruine";
        if ($code >= 61 && $code <= 65)
            return "Pluie";
        if ($code >= 71 && $code <= 77)
            return "Neige";
        if ($code >= 80 && $code <= 82)
            return "Averses";
        if ($code >= 95 && $code <= 99)
            return "Orage";
        return "Conditions variables";
    }

    private function generateAgriculturalAdvice(array $data): string
    {
        if ($data['humidity'] > 80 && $data['temperature'] > 20) {
            return "⚠️ Alerte Humidité: Risque élevé de maladies fongiques (Mildiou, Oïdium). Évitez l'irrigation par aspersion.";
        }
        if ($data['precipitation'] > 5) {
            return "🌧️ Pluie prévue: Suspendez les traitements phytosanitaires et l'irrigation.";
        }
        if ($data['temperature'] > 35) {
            return "🔥 Canicule: Assurez une irrigation suffisante en début ou fin de journée pour éviter le stress hydrique.";
        }
        if ($data['temperature'] < 5) {
            return "❄️ Risque de gel: Protégez les cultures sensibles et les semis.";
        }
        if ($data['weatherCode'] >= 95) {
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
        // "2026-02-15T14:00" → "15/02 à 14h00"
        try {
            $dt = new \DateTime($isoTime);
            return $dt->format('d/m à H\hi');
        } catch (\Exception $e) {
            return $isoTime;
        }
    }
}

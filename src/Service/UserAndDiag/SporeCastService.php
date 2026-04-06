<?php

namespace App\Service\UserAndDiag;

class SporeCastService
{
    private WeatherService $weatherService;

    public function __construct(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }

    /**
     * Analyzes if a disease at a specific location is likely to spread via wind.
     */
    public function analyzeSourceLocation(float $lat, float $lon, string $diseaseName): array
    {
        // For simulation, we assume some diseases are airborne
        // Using broader keywords to match various diagnostic phrases via substring search
        $airborneDiseases = ['Blight', 'Rust', 'Mildew', 'Mildiou', 'Rouille', 'Puceron', 'Chenille', 'Virus', 'Bactéri', 'Alternari', 'Pourriture', 'Spore'];

        $isAirborne = false;
        foreach ($airborneDiseases as $a) {
            if (stripos($diseaseName, $a) !== false) {
                $isAirborne = true;
                break;
            }
        }

        if (!$isAirborne) {
            return ['isContagious' => false];
        }

        // Fetch real weather data from the injected WeatherService (Open-Meteo API)
        $weather = $this->weatherService->getCurrentWeather($lat, $lon);

        if ($weather !== null) {
            $angle = $weather['windDirection'];
            $windSpeedKmh = $weather['windSpeed'];

            // Calculate a plausible blast radius based on the real wind speed 
            // e.g. 20 km/h wind creates a ~10km radius. Minimum 1km, Maximum 50km
            $radius = max(1.0, min(50.0, $windSpeedKmh * 0.5));

            $directionLabel = $weather['windDirectionString'];
        } else {
            // Fallback deterministic simulation based on location/time
            mt_srand((int) ($lat * 1000) ^ (int) ($lon * 1000) ^ date('z'));
            $angle = mt_rand(0, 360);
            $radius = mt_rand(10, 50) / 10.0; // 1-5 km
            $directionLabel = $this->getDirectionLabel($angle);
        }

        return [
            'isContagious' => true,
            'travelAngle' => (float) $angle,
            'blastRadiusKm' => (float) $radius,
            'windDirectionLabel' => $directionLabel
        ];
    }

    private function getDirectionLabel(float $angle): string
    {
        $directions = ['Nord', 'Nord-Est', 'Est', 'Sud-Est', 'Sud', 'Sud-Ouest', 'Ouest', 'Nord-Ouest', 'Nord'];
        return $directions[(int) round($angle / 45)];
    }
}

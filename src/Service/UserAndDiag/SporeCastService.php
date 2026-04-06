<?php

namespace App\Service\UserAndDiag;

class SporeCastService
{
    /**
     * Analyzes if a disease at a specific location is likely to spread via wind.
     */
    public function analyzeSourceLocation(float $lat, float $lon, string $diseaseName): array
    {
        // For simulation, we assume some diseases are airborne
        $airborneDiseases = ['Late Blight', 'Rust', 'Mildew', 'Mildiou', 'Rouille', 'Puceron', 'Chenille', 'Virus', 'Bactérie', 'Pourriture Alternaire'];

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

        // Deterministic simulation based on location/time
        mt_srand((int) ($lat * 1000) ^ (int) ($lon * 1000) ^ date('z'));

        $angle = mt_rand(0, 360);
        $radius = mt_rand(10, 50) / 10.0; // 1-5 km

        return [
            'isContagious' => true,
            'travelAngle' => (float) $angle,
            'blastRadiusKm' => (float) $radius,
            'windDirectionLabel' => $this->getDirectionLabel($angle)
        ];
    }

    private function getDirectionLabel(float $angle): string
    {
        $directions = ['Nord', 'Nord-Est', 'Est', 'Sud-Est', 'Sud', 'Sud-Ouest', 'Ouest', 'Nord-Ouest', 'Nord'];
        return $directions[round($angle / 45)];
    }
}

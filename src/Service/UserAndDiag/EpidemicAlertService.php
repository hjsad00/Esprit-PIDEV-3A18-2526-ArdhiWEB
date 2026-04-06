<?php

namespace App\Service\UserAndDiag;

use App\Repository\UserAndDiag\DiagnosticRepository;

class EpidemicAlertService
{
    private DiagnosticRepository $diagnosticRepository;

    public function __construct(DiagnosticRepository $diagnosticRepository)
    {
        $this->diagnosticRepository = $diagnosticRepository;
    }

    /**
     * Returns a list of unique diseases detected in the area.
     */
    public function getActiveDiseases(float $lat, float $lon, float $radiusKm = 25.0): array
    {
        $diagnostics = $this->diagnosticRepository->findNearby($lat, $lon, $radiusKm);

        $diseases = [];
        foreach ($diagnostics as $d) {
            $result = $d->getResultatIa();
            if (!$result)
                continue;

            // Skip records that don't have GPS coordinates to prevent TypeError in calculateDistance
            if ($d->getLatitude() === null || $d->getLongitude() === null) {
                continue;
            }

            // Result format can be "Plant - Disease" or "Plant : Disease"
            $parts = preg_split('/[:\-]/', $result);
            $diseaseName = trim($parts[1] ?? $result);
            $plantName = trim($parts[0] ?? 'Plante');

            if (!isset($diseases[$diseaseName])) {
                $diseases[$diseaseName] = [
                    'diseaseName' => $diseaseName,
                    'plantName' => $plantName,
                    'severityLevel' => $d->getSeverity() ?: 'Modéré',
                    'nearestDistanceKm' => $this->calculateDistance($lat, $lon, $d->getLatitude(), $d->getLongitude()),
                    'count' => 1,
                    'icon' => $this->getDiseaseIcon($diseaseName)
                ];
            } else {
                $diseases[$diseaseName]['count']++;
                $dist = $this->calculateDistance($lat, $lon, $d->getLatitude(), $d->getLongitude());
                if ($dist < $diseases[$diseaseName]['nearestDistanceKm']) {
                    $diseases[$diseaseName]['nearestDistanceKm'] = $dist;
                }
            }
        }

        // Convert to indexed array and sort by distance
        $resultArray = array_values($diseases);
        usort($resultArray, fn($a, $b) => $a['nearestDistanceKm'] <=> $b['nearestDistanceKm']);

        return $resultArray;
    }

    /**
     * Returns text alerts based on regional activities.
     */
    public function getRegionalAlerts(float $lat, float $lon, float $radiusKm = 25.0, int $days = 14): array
    {
        $diseases = $this->getActiveDiseases($lat, $lon, $radiusKm);
        $alerts = [];

        foreach ($diseases as $d) {
            if ($d['severityLevel'] === 'CRITICAL' || $d['severityLevel'] === 'Élevé') {
                $alerts[] = [
                    'message' => sprintf("⚠️ ALERTE : %s détecté sur %s à seulement %.1fkm de chez vous !", $d['diseaseName'], $d['plantName'], $d['nearestDistanceKm']),
                    'type' => 'CRITICAL'
                ];
            }
        }

        return $alerts;
    }

    /**
     * Returns stats: [unique_diseases_count, total_reports_count]
     */
    public function getRegionalStats(float $lat, float $lon, float $radiusKm = 25.0): array
    {
        $diagnostics = $this->diagnosticRepository->findNearby($lat, $lon, $radiusKm);

        $uniqueDiseases = [];
        foreach ($diagnostics as $d) {
            $result = $d->getResultatIa();
            if ($result) {
                $uniqueDiseases[$result] = true;
            }
        }

        return [
            count($uniqueDiseases),
            count($diagnostics)
        ];
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function getDiseaseIcon(string $diseaseName): string
    {
        $diseaseName = strtolower($diseaseName);
        if (str_contains($diseaseName, 'mildiou'))
            return '🍄';
        if (str_contains($diseaseName, 'oïdium'))
            return '🌫️';
        if (str_contains($diseaseName, 'rouille'))
            return '🍂';
        if (str_contains($diseaseName, 'puceron'))
            return '🐜';
        if (str_contains($diseaseName, 'chenille'))
            return '🐛';
        if (str_contains($diseaseName, 'virus'))
            return '🧬';
        if (str_contains($diseaseName, 'bactérie'))
            return '🦠';
        return '🌱';
    }
}

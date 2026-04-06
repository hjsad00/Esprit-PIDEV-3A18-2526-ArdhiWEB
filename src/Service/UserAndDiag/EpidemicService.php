<?php

namespace App\Service\UserAndDiag;

use Doctrine\DBAL\Connection;

class EpidemicService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get active diseases in the user's region (last 14 days).
     */
    public function getActiveDiseases(float $lat, float $lon, float $radiusKm = 50.0): array
    {
        $sql = "SELECT 
                SUBSTRING_INDEX(resultat_ia, ' - ', -1) AS disease_name,
                COUNT(*) AS report_count,
                MIN(IFNULL(6371 * ACOS(LEAST(1.0, COS(RADIANS(:lat)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon)) 
                + SIN(RADIANS(:lat)) * SIN(RADIANS(latitude)))), 0)) AS nearest_km
                FROM diagnostic
                WHERE resultat_ia IS NOT NULL AND resultat_ia != ''
                AND resultat_ia NOT LIKE '%Healthy%' AND resultat_ia NOT LIKE '%Sain%'
                GROUP BY disease_name
                ORDER BY report_count DESC
                LIMIT 8";

        $result = $this->connection->executeQuery($sql, ['lat' => $lat, 'lon' => $lon]);

        $diseases = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $severity = $this->calculateSeverity($row['report_count']);
            $diseases[] = [
                'name' => $this->cleanName($row['disease_name']),
                'count' => (int) $row['report_count'],
                'distance' => (float) $row['nearest_km'],
                'severity' => $severity,
                'icon' => $this->getSeverityIcon($severity)
            ];
        }

        return $diseases;
    }

    /**
     * Get specific regional alerts (last N days).
     */
    public function getRegionalAlerts(float $lat, float $lon, float $radiusKm = 25.0, int $lastDays = 7): array
    {
        $sql = "SELECT 
                SUBSTRING_INDEX(resultat_ia, ' - ', -1) AS disease_name,
                COUNT(*) AS report_count,
                MIN(IFNULL(6371 * ACOS(LEAST(1.0, COS(RADIANS(:lat)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon)) 
                + SIN(RADIANS(:lat)) * SIN(RADIANS(latitude)))), 0)) AS nearest_km,
                IFNULL(DATEDIFF(NOW(), MIN(date_scan)), 0) AS days_active
                FROM diagnostic
                WHERE resultat_ia IS NOT NULL AND resultat_ia != ''
                GROUP BY disease_name
                HAVING report_count >= 1
                ORDER BY report_count DESC
                LIMIT 10";

        $result = $this->connection->executeQuery($sql, ['lat' => $lat, 'lon' => $lon]);

        $alerts = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $name = $this->cleanName($row['disease_name']);
            $dist = (float) $row['nearest_km'];
            $distStr = $dist < 1 ? round($dist * 1000) . 'm' : round($dist, 1) . 'km';

            $alerts[] = [
                'name' => $name,
                'message' => sprintf("⚠️ %s détecté à %s de votre position. %d agriculteur(s) l'ont signalé récemment.", $name, $distStr, $row['report_count'])
            ];
        }

        return $alerts;
    }

    private function calculateSeverity(int $count): string
    {
        if ($count >= 10)
            return 'Élevé';
        if ($count >= 3)
            return 'Modéré';
        return 'Faible';
    }

    private function getSeverityIcon(string $severity): string
    {
        return match ($severity) {
            'Élevé' => '🔴',
            'Modéré' => '🟡',
            default => '🟢'
        };
    }

    private function cleanName(?string $name): string
    {
        if (!$name)
            return 'Maladie inconnue';
        return trim(str_replace('_', ' ', $name)) ?: 'Maladie inconnue';
    }
}

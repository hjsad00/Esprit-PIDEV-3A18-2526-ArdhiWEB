<?php

namespace App\Service\UserAndDiag;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class NDVIService
{
    private HttpClientInterface $httpClient; // @phpstan-ignore property.onlyWritten
    private const STATS_API_URL = 'https://sh.dataspace.copernicus.eu/api/v1/statistics'; // @phpstan-ignore classConstant.unused

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function fetchSimulatedNDVIGrid(float $centerLat, float $centerLon, float $radiusKm, int $gridSize): array
    {
        $cells = [];
        $latRadius = $radiusKm / 111.0;
        $lonRadius = $radiusKm / (111.0 * cos(deg2rad($centerLat)));
        $cellSizeLat = (2 * $latRadius) / $gridSize;
        $cellSizeLon = (2 * $lonRadius) / $gridSize;
        $startLat = $centerLat - $latRadius;
        $startLon = $centerLon - $lonRadius;

        // Deterministic random seed based on coordinates
        mt_srand((int) ($centerLat * 1000000) ^ (int) ($centerLon * 1000000));

        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                $cellLat = $startLat + ($row + 0.5) * $cellSizeLat;
                $cellLon = $startLon + ($col + 0.5) * $cellSizeLon;

                $distFromCenter = sqrt(
                    pow(($cellLat - $centerLat) / $latRadius, 2) +
                    pow(($cellLon - $centerLon) / $lonRadius, 2)
                );

                $baseNdvi = 0.6 - ($distFromCenter * 0.3);
                $noise = (mt_rand(0, 1000) / 1000 - 0.5) * 0.25;
                $ndvi = max(-0.1, min(0.9, $baseNdvi + $noise));

                $cells[] = [
                    'lat' => $cellLat,
                    'lon' => $cellLon,
                    'ndvi' => round($ndvi, 3),
                    'size' => max($cellSizeLat, $cellSizeLon),
                    'color' => $this->ndviToColor($ndvi),
                    'label' => $this->ndviToLabel($ndvi)
                ];
            }
        }

        return $cells;
    }

    public function ndviToColor(float $ndvi): string
    {
        if ($ndvi < 0.1)
            return "#8B4513";
        if ($ndvi < 0.2)
            return "#D2691E";
        if ($ndvi < 0.3)
            return "#FFA500";
        if ($ndvi < 0.4)
            return "#FFD700";
        if ($ndvi < 0.5)
            return "#ADFF2F";
        if ($ndvi < 0.6)
            return "#7CFC00";
        if ($ndvi < 0.7)
            return "#32CD32";
        if ($ndvi < 0.8)
            return "#228B22";
        return "#006400";
    }

    public function ndviToLabel(float $ndvi): string
    {
        if ($ndvi < 0.1)
            return "Sol nu / Eau";
        if ($ndvi < 0.3)
            return "Végétation clairsemée";
        if ($ndvi < 0.5)
            return "Végétation modérée";
        if ($ndvi < 0.7)
            return "Végétation saine";
        return "Végétation dense";
    }
}

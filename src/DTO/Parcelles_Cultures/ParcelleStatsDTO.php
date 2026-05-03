<?php

namespace App\DTO\Parcelles_Cultures;

/**
 * DTO for Parcelle Statistics Aggregation
 * Used with Doctrine NEW syntax for type-safe aggregation queries
 */
class ParcelleStatsDTO
{
    public function __construct(
        public ?int $totalParcelles = 0,
        public ?float $totalSurface = 0.0,
        public ?int $parcellesActives = 0,
    ) {
    }
}

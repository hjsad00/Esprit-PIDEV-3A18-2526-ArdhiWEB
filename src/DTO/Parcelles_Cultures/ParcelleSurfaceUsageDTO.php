<?php

namespace App\DTO\Parcelles_Cultures;

/**
 * DTO for surface usage aggregation by parcelle.
 */
class ParcelleSurfaceUsageDTO
{
    public function __construct(
        public int $parcelleId,
        public float $totalSurface
    ) {
    }
}

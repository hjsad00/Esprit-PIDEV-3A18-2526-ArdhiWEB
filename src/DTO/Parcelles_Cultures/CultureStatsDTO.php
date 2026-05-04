<?php

namespace App\DTO\Parcelles_Cultures;

/**
 * DTO for Culture Statistics Aggregation
 * Used with Doctrine NEW syntax for type-safe aggregation queries
 */
class CultureStatsDTO
{
    public function __construct(
        public ?int $totalCultures = 0,
        public ?float $totalSurface = 0.0,
        public ?float $totalProduction = 0.0,
    ) {
    }
}

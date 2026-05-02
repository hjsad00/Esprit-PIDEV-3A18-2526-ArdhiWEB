<?php

namespace App\DTO\Parcelles_Cultures;

class ChartStatDTO
{
    public ?string $type;
    public int $count;
    public float $total;
    public float $avgRendement;

    public function __construct(
        ?string $type = null,
        mixed $count = 0,
        mixed $total = 0.0,
        mixed $avgRendement = 0.0
    ) {
        $this->type = $type;
        $this->count = (int)$count;
        $this->total = (float)$total;
        $this->avgRendement = (float)$avgRendement;
    }
}

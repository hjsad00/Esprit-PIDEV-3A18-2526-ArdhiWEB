<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\IrrigationRequest;

class IrrigationManager
{
    /**
     * Valide une demande d'irrigation selon les règles métier
     * 
     * Règles:
     * - Le volume d'eau (litres) doit être positif
     * - L'humidité doit être entre 0 et 100
     */
    public function validate(IrrigationRequest $irrigation): bool
    {
        // Règle 1: Volume d'eau positif
        $volume = $irrigation->getVolumeLitres();
        if ($volume === null || (float)$volume <= 0) {
            throw new \InvalidArgumentException('Le volume d\'eau doit être positif');
        }

        // Règle 2: Humidité valide (0-100%)
        $humidite = $irrigation->getHumidite();
        if ($humidite === null) {
            throw new \InvalidArgumentException('L\'humidité est obligatoire');
        }
        $humiditeValue = (float)$humidite;
        if ($humiditeValue < 0 || $humiditeValue > 100) {
            throw new \InvalidArgumentException('L\'humidité doit être entre 0 et 100%');
        }

        return true;
    }
}

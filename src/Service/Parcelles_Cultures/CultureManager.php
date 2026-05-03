<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;

class CultureManager
{
    /**
     * Valide une culture selon les règles métier
     * 
     * Règles:
     * - La surface doit être positive
     * - La date de récolte doit être après la date de plantation
     */
    public function validate(Culture $culture): bool
    {
        // Règle 1: Surface positive
        $surface = (float) $culture->getSurfaceUtilisee();
        if ($surface <= 0) {
            throw new \InvalidArgumentException('La surface doit être positive');
        }

        // Règle 2: Date récolte > date plantation
        $datePlantation = $culture->getDatePlantation();
        $dateRecolte = $culture->getDateRecoltePrevue();

        if ($dateRecolte <= $datePlantation) {
            throw new \InvalidArgumentException('La date de récolte doit être postérieure à la date de plantation');
        }

        return true;
    }
}

<?php

namespace App\Service\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;

class MaterielManager
{
    public function validate(Materiel $materiel): bool
    {
        if (empty($materiel->getNom())) {
            throw new \InvalidArgumentException('Le nom du matériel est obligatoire');
        }

        if ($materiel->getHeuresUtilisation() < 0) {
            throw new \InvalidArgumentException('Les heures d\'utilisation ne peuvent pas être négatives');
        }

        return true;
    }
}

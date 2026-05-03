<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use InvalidArgumentException;

class EvenementManager
{
    public function validate(Evenement $evenement): bool
    {
        if (empty(trim((string) $evenement->getTitre()))) {
            throw new InvalidArgumentException('Le titre est obligatoire');
        }

        if (empty(trim((string) $evenement->getLieu()))) {
            throw new InvalidArgumentException('Le lieu est obligatoire');
        }

        if ($evenement->getNombrePlacesMax() <= 0) {
            throw new InvalidArgumentException('Le nombre de places doit être supérieur à zéro');
        }

        $dateDebut = $evenement->getDateDebut();
        $dateFin = $evenement->getDateFin();

        if (!$dateDebut || !$dateFin || $dateFin <= $dateDebut) {
            throw new InvalidArgumentException('La date de fin doit être postérieure à la date de début');
        }

        return true;
    }
}

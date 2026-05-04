<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Participation;
use InvalidArgumentException;

class ParticipationManager
{
    public function validate(Participation $participation): bool
    {
        $nombrePersonnes = $participation->getNombrePersonnes();
        if ($nombrePersonnes < 1 || $nombrePersonnes > 10) {
            throw new InvalidArgumentException('Le nombre de personnes doit être entre 1 et 10');
        }

        $note = $participation->getNote();
        if ($note < 0 || $note > 5) {
            throw new InvalidArgumentException('La note doit être entre 0 et 5');
        }

        if (!in_array($participation->getStatut(), ['CONFIRME', 'PRESENT', 'ANNULE'], true)) {
            throw new InvalidArgumentException('Le statut est invalide');
        }

        return true;
    }
}

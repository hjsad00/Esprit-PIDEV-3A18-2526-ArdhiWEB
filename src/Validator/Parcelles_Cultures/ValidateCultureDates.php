<?php

namespace App\Validator\Parcelles_Cultures;

use Symfony\Component\Validator\Constraint;

/**
 * Validateur pour vérifier que date_plantation < date_recolte_prevue
 */
#[\Attribute]
class ValidateCultureDates extends Constraint
{
    public $message = 'La date de plantation doit être antérieure à la date de récolte prévue.';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}

<?php

namespace App\Validator\Parcelles_Cultures;

use Symfony\Component\Validator\Constraint;

/**
 * Validateur pour vérifier que la surface utilisée ne dépasse pas la surface de la parcelle
 */
#[\Attribute]
class ValidateSurfaceConstraint extends Constraint
{
    public $message = 'La surface utilisée ne peut pas dépasser la surface totale de la parcelle.';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}

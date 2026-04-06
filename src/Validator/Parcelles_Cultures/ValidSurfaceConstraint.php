<?php

namespace App\Validator\Parcelles_Cultures;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidSurfaceConstraint extends Constraint
{
    public string $message = 'La surface utilisée ne peut pas dépasser la surface disponible de la parcelle.';

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}

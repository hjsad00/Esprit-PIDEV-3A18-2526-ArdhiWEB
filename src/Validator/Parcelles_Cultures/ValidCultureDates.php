<?php

namespace App\Validator\Parcelles_Cultures;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidCultureDates extends Constraint
{
    public string $message = 'La date de plantation doit être antérieure à la date de récolte.';

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}

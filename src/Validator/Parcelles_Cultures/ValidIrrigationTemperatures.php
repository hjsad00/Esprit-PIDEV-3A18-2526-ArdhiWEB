<?php

namespace App\Validator\Parcelles_Cultures;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidIrrigationTemperatures extends Constraint
{
    public string $message = 'La température min doit être inférieure ou égale à la température moyenne, et la température moyenne inférieure ou égale à la température max.';

    public function getTargets(): array|string
    {
        return self::CLASS_CONSTRAINT;
    }
}

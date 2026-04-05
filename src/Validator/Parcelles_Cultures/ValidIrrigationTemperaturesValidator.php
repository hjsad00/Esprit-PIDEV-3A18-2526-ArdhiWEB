<?php

namespace App\Validator\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\IrrigationRequest;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidIrrigationTemperaturesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidIrrigationTemperatures) {
            throw new UnexpectedTypeException($constraint, ValidIrrigationTemperatures::class);
        }

        if (!$value instanceof IrrigationRequest) {
            throw new UnexpectedTypeException($value, IrrigationRequest::class);
        }

        $tmin = (float) ($value->getTemperatureMin() ?? 0);
        $tmoy = (float) ($value->getTemperatureMoyenne() ?? 0);
        $tmax = (float) ($value->getTemperatureMax() ?? 0);

        if (!($tmin <= $tmoy && $tmoy <= $tmax)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('temperature_moyenne')
                ->addViolation();
        }
    }
}

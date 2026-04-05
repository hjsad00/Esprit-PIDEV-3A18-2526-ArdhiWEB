<?php

namespace App\Validator\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidCultureDatesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCultureDates) {
            throw new UnexpectedTypeException($constraint, ValidCultureDates::class);
        }

        if (!$value instanceof Culture) {
            throw new UnexpectedTypeException($value, Culture::class);
        }

        if (null === $value->getDatePlantation() || null === $value->getDateRecolteProvue()) {
            return;
        }

        if ($value->getDatePlantation() >= $value->getDateRecolteProvue()) {
            $this->context->buildViolation($constraint->message)
                ->atPath('date_recolte_prevue')
                ->addViolation();
        }
    }
}

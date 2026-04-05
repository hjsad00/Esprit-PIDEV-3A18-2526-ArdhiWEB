<?php

namespace App\Validator\Parcelles_Cultures;

use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validateur pour ValidateCultureDates
 */
class ValidateCultureDatesValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ValidateCultureDates) {
            throw new UnexpectedTypeException($constraint, ValidateCultureDates::class);
        }

        if (null === $value) {
            return;
        }

        // Vérifier que date_plantation < date_recolte_prevue
        if ($value->getDatePlantation() && $value->getDateRetePrevue()) {
            if ($value->getDatePlantation() >= $value->getDateRetePrevue()) {
                $this->context->buildViolation($constraint->message)
                    ->addViolation();
            }
        }
    }
}

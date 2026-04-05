<?php

namespace App\Validator\Parcelles_Cultures;

use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validateur pour ValidateSurfaceConstraint
 */
class ValidateSurfaceConstraintValidator extends ConstraintValidator
{
    private $cultureRepository;

    public function __construct(CultureRepository $cultureRepository)
    {
        $this->cultureRepository = $cultureRepository;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ValidateSurfaceConstraint) {
            throw new UnexpectedTypeException($constraint, ValidateSurfaceConstraint::class);
        }

        if (null === $value) {
            return;
        }

        // Vérifier que la surface utilisée <= surface de la parcelle
        if ($value->getParcelle() && $value->getSurfaceUtilisee()) {
            $totalSurface = $this->cultureRepository->getSurfaceUtiliseeTotalByParcelle(
                $value->getParcelle()->getId()
            );

            // Exclure la culture actuelle du calcul si elle existe
            if ($value->getId()) {
                $totalSurface -= $value->getSurfaceUtilisee();
            }

            if ($totalSurface + $value->getSurfaceUtilisee() > $value->getParcelle()->getSurface()) {
                $this->context->buildViolation($constraint->message)
                    ->addViolation();
            }
        }
    }
}

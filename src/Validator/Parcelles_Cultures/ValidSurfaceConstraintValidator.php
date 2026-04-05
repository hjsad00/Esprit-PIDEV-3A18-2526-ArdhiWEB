<?php

namespace App\Validator\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidSurfaceConstraintValidator extends ConstraintValidator
{
    public function __construct(private CultureRepository $cultureRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSurfaceConstraint) {
            throw new UnexpectedTypeException($constraint, ValidSurfaceConstraint::class);
        }

        if (!$value instanceof Culture) {
            throw new UnexpectedTypeException($value, Culture::class);
        }

        if (null === $value->getParcelle() || null === $value->getSurfaceUtilisee()) {
            return;
        }

        $parcelle = $value->getParcelle();
        $surfaceParc = (float) $parcelle->getSurface();
        $surfaceNewCulture = (float) $value->getSurfaceUtilisee();

        // Somme des autres cultures sur cette parcelle
        $totalSurfaceOthers = $this->cultureRepository->getSurfaceUtiliseeParParcelle(
            $parcelle->getId(),
            $value->getId() // Exclure la culture en cours
        );

        if (($totalSurfaceOthers + $surfaceNewCulture) > $surfaceParc) {
            $this->context->buildViolation($constraint->message)
                ->atPath('surface_utilisee')
                ->addViolation();
        }
    }
}

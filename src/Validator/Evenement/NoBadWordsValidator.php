<?php

namespace App\Validator\Evenement;

use App\Service\Evenement\BadWordFilterService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoBadWordsValidator extends ConstraintValidator
{
    public function __construct(private BadWordFilterService $badWordFilterService)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoBadWords) {
            throw new UnexpectedTypeException($constraint, NoBadWords::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $badWords = $this->badWordFilterService->hasBadWords($value);

        if (!empty($badWords)) {
            $this->context->buildViolation($this->badWordFilterService->getErrorMessage($badWords))
                ->addViolation();
        }
    }
}

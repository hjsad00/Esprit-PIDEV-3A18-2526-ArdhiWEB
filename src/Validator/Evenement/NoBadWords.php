<?php

namespace App\Validator\Evenement;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NoBadWords extends Constraint
{
    public string $message = 'Votre texte contient des mots non autorisés. Veuillez les retirer.';

    public function getTargets(): string|array
    {
        return self::PROPERTY_TARGET;
    }
}

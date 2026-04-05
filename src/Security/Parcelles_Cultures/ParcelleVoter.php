<?php

namespace App\Security\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Parcelle;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ParcelleVoter extends Voter
{
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE]) && $subject instanceof Parcelle;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Si l'utilisateur n'est pas connecté
        if (!$user) {
            return false;
        }

        // L'admin peut toujours éditer/supprimer
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Les agriculteurs ne peuvent éditer/supprimer que leurs propres parcelles
        if (in_array('ROLE_FARMER', $user->getRoles())) {
            return $subject->getAgriculteur() === $user;
        }

        return false;
    }
}

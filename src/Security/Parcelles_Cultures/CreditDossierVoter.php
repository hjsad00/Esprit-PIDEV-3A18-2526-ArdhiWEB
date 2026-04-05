<?php

namespace App\Security\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\CreditDossier;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CreditDossierVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';
    public const EXPORT = 'EXPORT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::EXPORT]) && $subject instanceof CreditDossier;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Si l'utilisateur n'est pas connecté
        if (!$user) {
            return false;
        }

        // L'admin peut tout faire
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Les agriculteurs ne peuvent voir/éditer/exporter que leurs propres dossiers
        if (in_array('ROLE_FARMER', $user->getRoles())) {
            return $subject->getUser() === $user;
        }

        return false;
    }
}

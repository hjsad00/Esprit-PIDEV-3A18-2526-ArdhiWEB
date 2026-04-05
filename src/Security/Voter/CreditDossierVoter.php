<?php

namespace App\Security\Voter;

use App\Entity\Parcelles_Cultures\CreditDossier;
use App\Entity\UserAndDiag\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class CreditDossierVoter extends Voter
{
    public const VIEW = 'view';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::DELETE])
            && $subject instanceof CreditDossier;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var CreditDossier $dossier */
        $dossier = $subject;

        return match ($attribute) {
            self::VIEW, self::DELETE => $this->canAccess($dossier, $user),
            default => false,
        };
    }

    private function canAccess(CreditDossier $dossier, UserInterface $user): bool
    {
        // Administrateurs can access everything
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Farmers can only access their own dossiers
        $parcelle = $dossier->getParcelle();
        if (!$parcelle) {
            return false;
        }

        return $parcelle->getAgriculteur() === $user;
    }
}

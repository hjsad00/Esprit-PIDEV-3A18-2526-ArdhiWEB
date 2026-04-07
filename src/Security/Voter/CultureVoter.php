<?php

namespace App\Security\Voter;

use App\Entity\Parcelles_Cultures\Culture;
use App\Entity\UserAndDiag\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class CultureVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Culture;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var Culture $culture */
        $culture = $subject;

        return match ($attribute) {
            self::VIEW, self::EDIT, self::DELETE => $this->canAccess($culture, $user),
            default => false,
        };
    }

    private function canAccess(Culture $culture, UserInterface $user): bool
    {
        // Administrateurs can access everything
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        if (!$user instanceof User) {
            return false;
        }

        // Farmers can only access cultures on their own parcelles
        $parcelle = $culture->getParcelle();
        if (!$parcelle) {
            return false;
        }

        $owner = $parcelle->getAgriculteur();
        return $owner && $owner->getId() === $user->getId();
    }
}

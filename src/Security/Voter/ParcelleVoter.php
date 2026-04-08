<?php

namespace App\Security\Voter;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\UserAndDiag\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ParcelleVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Parcelle;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var Parcelle $parcelle */
        $parcelle = $subject;

        return match ($attribute) {
            self::VIEW, self::EDIT, self::DELETE => $this->canAccess($parcelle, $user),
            default => false,
        };
    }

    private function canAccess(Parcelle $parcelle, UserInterface $user): bool
    {
        // Administrateurs can access everything
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Farmers can only access their own parcelles
        if (!$user instanceof User) {
            return false;
        }

        $owner = $parcelle->getAgriculteur();
        return $owner && $owner->getId() === $user->getId();
    }
}

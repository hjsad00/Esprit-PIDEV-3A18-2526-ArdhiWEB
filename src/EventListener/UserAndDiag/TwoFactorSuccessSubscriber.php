<?php

namespace App\EventListener\UserAndDiag;

use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TwoFactorSuccessSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwoFactorAuthenticationEvents::SUCCESS => 'onTwoFactorSuccess',
        ];
    }

    public function onTwoFactorSuccess(TwoFactorAuthenticationEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();

        // Safely check if the user is our App User entity
        if ($user instanceof \App\Entity\UserAndDiag\User) {
            // Forcefully clear the fields natively
            $user->setEmailAuthCode(null);

            // Persist and vigorously flush to update the database
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
    }
}

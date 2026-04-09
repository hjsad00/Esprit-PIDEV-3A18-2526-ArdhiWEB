<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'fr')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        // Si la locale est définie dans la session, on l'utilise
        if ($locale = $request->getSession()->get('_locale')) {
            $request->setLocale($locale);
        } else {
            // Sinon on utilise la locale par défaut et on la met dans la session
            $request->setLocale($this->defaultLocale);
            $request->getSession()->set('_locale', $this->defaultLocale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Le 20 permet de l'exécuter avant les autres écouteurs
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}

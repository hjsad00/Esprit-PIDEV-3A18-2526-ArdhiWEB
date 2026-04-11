<?php

namespace App\Controller\EmployeTache;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'app_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        // On stocke la locale en session
        $request->getSession()->set('_locale', $locale);

        // On redirige vers la page précédente ou l'accueil
        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_home'));
    }
}

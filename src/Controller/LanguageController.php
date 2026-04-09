<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LanguageController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        // On vérifie que la langue est bien dans celles qu'on supporte (fr, en, ar)
        if (in_array($locale, ['fr', 'en', 'ar'])) {
            $request->getSession()->set('_locale', $locale);
        }

        // Retour à la page précédente
        $referer = $request->headers->get('referer');
        if (!$referer) {
            return $this->redirectToRoute('app_home'); // fallback
        }

        return $this->redirect($referer);
    }
}

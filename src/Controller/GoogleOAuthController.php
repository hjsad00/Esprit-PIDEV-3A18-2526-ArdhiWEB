<?php

namespace App\Controller;

use App\Service\MaterielEtMaintenance\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoogleOAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'app_google_connect')]
    public function connectGoogle(GoogleCalendarService $calendarService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $client = $calendarService->getClient();
        $redirectUri = $this->generateUrl('app_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $client->setRedirectUri($redirectUri);
        
        return $this->redirect($client->createAuthUrl());
    }

    #[Route('/connect/google/check', name: 'app_google_check')]
    public function checkGoogle(Request $request, GoogleCalendarService $calendarService, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $code = $request->query->get('code');
        if (!$code) {
            $this->addFlash('error', 'Annulation de la connexion avec Google Calendar.');
            return $this->redirectToRoute('app_maintenance_index');
        }

        $client = $calendarService->getClient();
        $redirectUri = $this->generateUrl('app_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $client->setRedirectUri($redirectUri);

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                throw new \Exception($token['error_description'] ?? 'Erreur OAuth Google');
            }

            /** @var \App\Entity\UserAndDiag\User $user */
            $user = $this->getUser();
            
            $user->setGoogleAccessToken($token['access_token']);
            if (isset($token['refresh_token'])) {
                $user->setGoogleRefreshToken($token['refresh_token']);
            }
            
            $em->flush();
            
            $this->addFlash('success', '✅ Votre calendrier Google a bien été relié avec succès ! Vos maintenances s\'ajouteront automatiquement.');
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Impossible de lier le compte : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_maintenance_new');
    }
}

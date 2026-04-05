<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\Diagnostic;
use App\Service\UserAndDiag\ChatbotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/chatbot')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ChatbotController extends AbstractController
{
    #[Route('/{id}', name: 'app_user_and_diag_chatbot', methods: ['GET'])]
    public function index(Diagnostic $diagnostic, ChatbotService $chatbotService): Response
    {
        // Check if the diagnostic belongs to the current user
        if ($diagnostic->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à ce diagnostic.");
        }

        $sessionId = 'diag_' . $diagnostic->getId();
        $history = $chatbotService->getHistory($sessionId);

        return $this->render('UserAndDiag/user_diagnostic/chat.html.twig', [
            'diagnostic' => $diagnostic,
            'history' => $history,
            'sessionId' => $sessionId
        ]);
    }

    #[Route('/{id}/send', name: 'app_user_and_diag_chatbot_send', methods: ['POST'])]
    public function send(Request $request, Diagnostic $diagnostic, ChatbotService $chatbotService): Response
    {
        // Check security
        if ($diagnostic->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $userMessage = $request->request->get('message');
        if (!$userMessage) {
            return $this->json(['error' => 'Message vide.'], 400);
        }

        $sessionId = 'diag_' . $diagnostic->getId();

        // Construct System Context based on Diagnostic Data
        $systemContext = sprintf(
            "Tu es Ardhi-IA, un expert agronome. Tu aides un agriculteur avec son diagnostic. " .
            "CONTEXTE DU DIAGNOSTIC : " .
            "Plante identifiée : %s. " .
            "Résultat : %s. " .
            "Gravité : %s. " .
            "Localisation : %s. " .
            "Réponds de manière concise, professionnelle et encourageante. Ton but est d'aider à appliquer le traitement recommandé.",
            $diagnostic->getResultatIa(),
            $diagnostic->getResultatIa(),
            $diagnostic->getSeverity(),
            $diagnostic->getLocationLabel()
        );

        $aiResponse = $chatbotService->chat($sessionId, $systemContext, $userMessage);

        return $this->json([
            'response' => $aiResponse
        ]);
    }

    #[Route('/{id}/clear', name: 'app_user_and_diag_chatbot_clear', methods: ['POST'])]
    public function clear(Diagnostic $diagnostic, ChatbotService $chatbotService): Response
    {
        if ($diagnostic->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $chatbotService->clearHistory('diag_' . $diagnostic->getId());
        return $this->json(['success' => true]);
    }
}

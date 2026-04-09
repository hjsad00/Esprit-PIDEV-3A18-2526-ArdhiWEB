<?php

namespace App\Controller\EmployeTache;

use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\RhChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe-tache/chatbot')]
#[IsGranted('ROLE_AGRICULTEUR')]
class RhChatbotController extends AbstractController
{
    #[Route('/send', name: 'app_rh_chatbot_send', methods: ['POST'])]
    public function send(Request $request, RhChatbotService $chatbotService, AgriculteurContextService $ctx): JsonResponse
    {
        $idAgriculteur = $ctx->getActiveAgriculteurId();
        if (!$idAgriculteur) {
            return new JsonResponse(['error' => 'Agriculteur non trouvé dans le contexte'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        if (empty($message)) {
            return new JsonResponse(['error' => 'Message vide'], 400);
        }

        $response = $chatbotService->processMessage($message, $idAgriculteur);

        return new JsonResponse($response);
    }
}

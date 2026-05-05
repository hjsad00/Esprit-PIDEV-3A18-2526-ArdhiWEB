<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Repository\MaterielEtMaintenance\MaterielRepository;
use App\Service\MaterielEtMaintenance\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/materiel-et-maintenance/chatbot', name: 'app_chatbot_')]
class ChatbotController extends AbstractController
{
    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request, ChatbotService $chatbotService, MaterielRepository $materielRepository): JsonResponse
    {
        $session = $request->getSession();
        $history = $session->get('chatbot_history', []);
        
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return new JsonResponse(['error' => 'Message vide'], 400);
        }

        // Ajouter le message de l'utilisateur à l'historique
        $history[] = ['role' => 'user', 'content' => $userMessage];

        // Récupérer le contexte du matériel de l'utilisateur
        $materiels = $materielRepository->findBy(['user' => $this->getUser()]);
        $context = "Liste des matériels de cet agriculteur :\n";
        foreach ($materiels as $m) {
            $heuresRelatves = $m->getHeuresUtilisation() - $m->getDerniereMaintenanceHeures();
            $context .= sprintf(
                "- %s (%s) : État %s, Statut %s, Heures : %d/%dh, Prochaine maintenance : %s\n",
                $m->getNom(),
                $m->getType(),
                $m->getEtat(),
                $m->getStatut(),
                $heuresRelatves,
                $m->getSeuilMaintenanceHeures(),
                $m->getDateProchaineMaintenance() ? $m->getDateProchaineMaintenance()->format('d/m/Y') : 'inconnue'
            );
        }

        // Obtenir la réponse de l'IA
        $aiResponse = $chatbotService->getResponse($history, $context);

        // Ajouter la réponse à l'historique
        $history[] = ['role' => 'assistant', 'content' => $aiResponse];
        
        // Limiter l'historique pour éviter des requêtes trop lourdes (ex: les 10 derniers messages)
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        $session->set('chatbot_history', $history);

        return new JsonResponse([
            'message' => $aiResponse
        ]);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $request->getSession()->remove('chatbot_history');
        return new JsonResponse(['success' => true]);
    }
}

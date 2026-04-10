<?php

namespace App\Controller\EmployeTache;

use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employe-tache/chatbot')]
class RhChatbotController extends AbstractController
{
    #[Route('/send', name: 'app_rh_chatbot_send', methods: ['POST'])]
    public function send(
        Request $request,
        ChatbotService $chatbotService,
        AgriculteurContextService $ctx
    ): JsonResponse {
        $idAgriculteur = $ctx->getActiveAgriculteurId();
        if (!$idAgriculteur) {
            return new JsonResponse(['error' => 'Accès refusé ou contexte non défini.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        if (empty($message)) {
            return new JsonResponse(['error' => 'Message vide.'], 400);
        }

        $response = $chatbotService->traiterMessage($message, $idAgriculteur);

        // Prepare JSON data manually to ensure RecommandationResult objects are properly handled
        $recs = [];
        foreach ($response->recommandations as $r) {
            $recs[] = [
                'employe' => [
                    'id' => $r->employe->getId(),
                    'prenom' => $r->employe->getPrenom(),
                    'nom' => $r->employe->getNom(),
                    'poste' => $r->employe->getPoste(),
                    'photoPath' => $r->employe->getPhotoPath(),
                    'telephone' => $r->employe->getTelephone(),
                    'email' => $r->employe->getEmail(),
                ],
                'scoreTotal' => $r->scoreTotal,
                'scoreCompetences' => $r->scoreCompetences,
                'scorePerformance' => $r->scorePerformance,
                'scoreDisponibilite' => $r->scoreDisponibilite,
                'scoreExperience' => $r->scoreExperience,
                'indiceConfiance' => $r->indiceConfiance,
                'raisonRecommandation' => $r->raisonRecommandation,
                'appreciation' => $r->getAppreciation(),
                'confianceLabel' => $r->getConfianceLabel(),
                'emoji' => $r->getEmoji(),
                'couleur' => $r->getCouleur(),
            ];
        }

        return new JsonResponse([
            'reponse' => $response->reponse,
            'intention' => $response->intention,
            'recommandations' => $recs,
            'disponibilites' => $response->disponibilites,
        ]);
    }
}

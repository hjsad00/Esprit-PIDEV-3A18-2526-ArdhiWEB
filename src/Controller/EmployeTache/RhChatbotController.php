<?php

namespace App\Controller\EmployeTache;

use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\ChatbotService;
use App\Service\EmployeTache\LocalMLIntentClassifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/employe-tache/chatbot')]
class RhChatbotController extends AbstractController
{
    /**
     * Mapping explicite des actions rapides (boutons UI) → intention.
     * Contourne entièrement le ML pour ces cas déterministes.
     *
     * Clé = valeur du champ "action" envoyé par le JS du bouton.
     */
    private const QUICK_ACTIONS = [
        'recommander'   => 'RECOMMANDER_EMPLOYE',
        'performances'  => 'ANALYSER_PERFORMANCE',
        'disponibilite' => 'DISPONIBILITE',
        'comparer'      => 'COMPARER_TOP3',
        'competences'   => 'RECHERCHER_COMPETENCE',
        'aide'          => 'AIDE',
    ];

    #[Route('/send', name: 'app_rh_chatbot_send', methods: ['POST'])]
    public function send(
        Request $request,
        ChatbotService $chatbotService,
        AgriculteurContextService $ctx,
        TranslatorInterface $translator,
        LocalMLIntentClassifier $classifier,
    ): JsonResponse {
        $idAgriculteur = $ctx->getActiveAgriculteurId();
        if (!$idAgriculteur) {
            return new JsonResponse(['error' => 'Accès refusé ou contexte non défini.'], 403);
        }

        $data       = json_decode($request->getContent(), true) ?? [];
        $message    = trim($data['message']    ?? '');
        $action     = trim($data['action']     ?? '');
        $lastIntent = trim($data['lastIntent'] ?? '') ?: null; // ← contexte conversationnel

        if ($message === '' && $action === '') {
            return new JsonResponse(['error' => 'Message vide.'], 400);
        }

        // ── Bouton rapide avec action explicite → bypass ML total ────────
        if ($action !== '' && isset(self::QUICK_ACTIONS[$action])) {
            $intention       = self::QUICK_ACTIONS[$action];
            $messageEffectif = $message !== '' ? $message : $this->labelForAction($action, $translator);

            $response = $chatbotService->traiterMessageAvecIntention(
                $messageEffectif,
                $intention,
                $idAgriculteur,
                $lastIntent
            );

            return $this->buildJsonResponse($response, $translator);
        }

        // ── Message libre → classifier ML + règles ──────────────────────
        if ($message === '') {
            return new JsonResponse(['error' => 'Message vide.'], 400);
        }

        $response = $chatbotService->traiterMessage($message, $idAgriculteur, $lastIntent);
        return $this->buildJsonResponse($response, $translator);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function labelForAction(string $action, TranslatorInterface $t): string
    {
        return match ($action) {
            'recommander'   => $t->trans('chatbot.btn.recommend'),
            'performances'  => $t->trans('chatbot.btn.performance'),
            'disponibilite' => $t->trans('chatbot.btn.availability'),
            'comparer'      => $t->trans('chatbot.btn.compare'),
            'competences'   => $t->trans('chatbot.btn.skills'),
            'aide'          => $t->trans('chatbot.btn.help'),
            default         => $action,
        };
    }

    private function buildJsonResponse(
        \App\Service\EmployeTache\ChatbotResponse $response,
        TranslatorInterface $translator
    ): JsonResponse {
        $recs = [];
        foreach ($response->recommandations as $r) {
            $recs[] = [
                'employe' => [
                    'id'        => $r->employe->getId(),
                    'prenom'    => $r->employe->getPrenom(),
                    'nom'       => $r->employe->getNom(),
                    'poste'     => $r->employe->getPoste(),
                    'photoPath' => $r->employe->getPhotoPath(),
                    'telephone' => $r->employe->getTelephone(),
                    'email'     => $r->employe->getEmail(),
                ],
                'scoreTotal'           => $r->scoreTotal,
                'scoreCompetences'     => $r->scoreCompetences,
                'scorePerformance'     => $r->scorePerformance,
                'scoreDisponibilite'   => $r->scoreDisponibilite,
                'scoreExperience'      => $r->scoreExperience,
                'indiceConfiance'      => $r->indiceConfiance,
                'raisonRecommandation' => $r->raisonRecommandation,
                'appreciation'         => $translator->trans($r->getAppreciationKey()),
                'confianceLabel'       => $translator->trans($r->getConfianceKey()),
                'emoji'                => $r->getEmoji(),
                'couleur'              => $r->getCouleur(),
            ];
        }

        return new JsonResponse([
            'reponse'         => $response->reponse,
            'intention'       => $response->intention,
            'recommandations' => $recs,
            'disponibilites'  => $response->disponibilites,
        ]);
    }
}
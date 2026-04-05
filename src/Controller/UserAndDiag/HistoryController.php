<?php

namespace App\Controller\UserAndDiag;

use App\Repository\UserAndDiag\DiagnosticRepository;
use App\Repository\UserAndDiag\TraitementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/history')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HistoryController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_history', methods: ['GET'])]
    public function index(Request $request, DiagnosticRepository $diagnosticRepository): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $keyword = $request->query->get('q');

        $diagnostics = $diagnosticRepository->findByUserAndKeyword($user->getId(), $keyword);

        return $this->render('UserAndDiag/diagnostic/history.html.twig', [
            'diagnostics' => $diagnostics,
            'keyword' => $keyword
        ]);
    }

    #[Route('/{id}/treatment', name: 'app_user_and_diag_history_treatment', methods: ['GET'])]
    public function getTreatment(
        int $id,
        DiagnosticRepository $diagnosticRepository,
        TraitementRepository $traitementRepository,
        \App\Service\UserAndDiag\SubscriptionFeatureService $featureService
    ): Response {
        $diagnostic = $diagnosticRepository->find($id);

        if (!$diagnostic || $diagnostic->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Diagnostic introuvable.'], 404);
        }

        $traitement = $traitementRepository->findOneBy(['diagnostic' => $diagnostic]);

        if (!$traitement) {
            return $this->json(['error' => 'Aucun traitement associé.'], 404);
        }

        $hasTreatmentAccess = $featureService->getFeatures($this->getUser())['accesTraitement'];

        return $this->json([
            'solutionNom' => $hasTreatmentAccess ? $traitement->getSolutionNom() : 'Verrouillé',
            'typeTraitement' => $hasTreatmentAccess ? $traitement->getTypeTraitement() : 'PREMIUM',
            'descriptionDetaillee' => $hasTreatmentAccess ? $traitement->getDescriptionDetaillee() : 'Veuillez souscrire à un abonnement premium pour consulter les historiques des traitements détaillés donnés par l\'IA.'
        ]);
    }
}

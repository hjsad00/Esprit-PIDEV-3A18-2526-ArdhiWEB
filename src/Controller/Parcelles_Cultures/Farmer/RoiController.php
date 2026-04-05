<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Service\Parcelles_Cultures\FinancialService;
use App\Service\Parcelles_Cultures\CultureService;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/roi', name: 'farmer_roi_')]
#[IsGranted('ROLE_FARMER')]
class RoiController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CultureRepository $cultureRepository
    ): Response {
        $user = $this->getUser();
        $cultures = $cultureRepository->getActiveByAgriculteur($user->getId());

        return $this->render('parcelles_cultures/farmer/roi/index.html.twig', [
            'cultures' => $cultures
        ]);
    }

    #[Route('/calculator', name: 'calculator', methods: ['GET', 'POST'])]
    public function calculator(
        Request $request,
        FinancialService $financialService,
        CultureRepository $cultureRepository
    ): Response {
        $result = null;
        $cultureId = $request->query->get('culture_id');

        if ($cultureId) {
            $culture = $cultureRepository->find($cultureId);

            // Vérification que la culture appartient à l'utilisateur
            if (!$culture || $culture->getParcelle()->getAgriculteur() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }

            $result = [
                'culture' => $culture,
                'production_estimee' => $culture->getSurfaceUtilisee() * $culture->getRendementEstime(),
                'production_reelle' => $financialService->calculerProductionReelle($culture),
                'cout_total' => $financialService->calculerCoutTotal($culture),
                'revenu_brut' => $financialService->calculerRevenuBrut($culture),
                'marge_brute' => $financialService->calculerMargeBrute($culture),
                'prix_seuil' => $financialService->calculerPrixSeuil($culture),
                'roi_pourcentage' => $financialService->calculerRoi($culture),
                'score_roi' => $financialService->calculerScoreRoi($culture)
            ];
        }

        $user = $this->getUser();
        $cultures = $cultureRepository->getActiveByAgriculteur($user->getId());

        return $this->render('parcelles_cultures/farmer/roi/calculator.html.twig', [
            'cultures' => $cultures,
            'result' => $result,
            'selected_culture_id' => $cultureId
        ]);
    }
}

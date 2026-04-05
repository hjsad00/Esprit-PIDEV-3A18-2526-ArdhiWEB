<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Service\Parcelles_Cultures\FinancialService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/roi', name: 'farmer_roi_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class RoiController extends AbstractController
{
    public function __construct(private FinancialService $financialService)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('parcelles_cultures/farmer/roi/index.html.twig');
    }

    #[Route('/calculator', name: 'calculator', methods: ['GET', 'POST'])]
    public function calculator(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $dto = new \App\DTO\Parcelles_Cultures\RoiDTO();
        $form = $this->createForm(\App\Form\Parcelles_Cultures\Type\RoiFormType::class, $dto);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $facteurClimatique = $this->financialService->calculerFacteurClimatique(
                (int) $dto->jours_canicule,
                (int) $dto->jours_excespluie,
                (int) $dto->jours_gel
            );

            $productionReelle = $this->financialService->calculerProductionReelle(
                (float) $dto->surface_ha,
                (float) $dto->rendement,
                $facteurClimatique
            );

            $coutTotal = $this->financialService->calculerCoutTotal(
                (float) $dto->cout_semences,
                (float) $dto->cout_engrais,
                (float) $dto->cout_main_oeuvre,
                (float) $dto->cout_irrigation,
                (float) $dto->cout_autres
            );

            $revenuBrut = $this->financialService->calculerRevenuBrut($productionReelle, (float) $dto->prix_vente);
            $margeBrute = $this->financialService->calculerMargeBrute($revenuBrut, $coutTotal);
            $prixSeuil = $this->financialService->calculerPrixSeuil($coutTotal, $productionReelle);
            $scoreROI = $this->financialService->calculerScoreROI($margeBrute, $coutTotal);

            $result = [
                'et0' => 0, // Placeholder
                'facteur_climatique' => $facteurClimatique,
                'production_reelle' => $productionReelle,
                'cout_total' => $coutTotal,
                'revenu_brut' => $revenuBrut,
                'marge_brute' => $margeBrute,
                'prix_seuil' => $prixSeuil,
                'score_roi' => $scoreROI,
            ];
        }

        return $this->render('parcelles_cultures/farmer/roi/calculator.html.twig', [
            'form' => $form->createView(),
            'roi_result' => $result
        ]);
    }
}

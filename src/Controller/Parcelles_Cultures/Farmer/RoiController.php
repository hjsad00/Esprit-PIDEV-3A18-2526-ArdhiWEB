<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Service\Parcelles_Cultures\FinancialService;
use App\Service\PythonRoiService;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Repository\Parcelles_Cultures\RoiAnalyseRepository;
use App\Entity\Parcelles_Cultures\RoiAnalyse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/farmer/roi', name: 'farmer_roi_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class RoiController extends AbstractController
{
    public function __construct(
        private FinancialService $financialService,
        private PythonRoiService $pythonRoiService,
        private ParcelleRepository $parcelleRepository,
        private RoiAnalyseRepository $roiAnalyseRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('parcelles_cultures/farmer/roi/index.html.twig');
    }

    #[Route('/calculator', name: 'calculator', methods: ['GET', 'POST'])]
    public function calculator(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        $user = $this->getUser();
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);

        $dto = new \App\DTO\Parcelles_Cultures\RoiDTO();
        $form = $this->createForm(\App\Form\Parcelles_Cultures\Type\RoiFormType::class, $dto, [
            'user_parcelles' => $parcelles
        ]);
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
            
            $capaciteRemboursement = $this->financialService->calculerCapaciteRemboursement($margeBrute);
            $montantPretMax = $this->financialService->calculerMontantPretMax($capaciteRemboursement, (int)$dto->duree_pret);
            $analyseRisque = $this->financialService->calculerScoreRisque($scoreROI, $facteurClimatique);

            $result = [
                'facteur_climatique' => $facteurClimatique,
                'production_reelle' => $productionReelle,
                'cout_total' => $coutTotal,
                'revenu_brut' => $revenuBrut,
                'marge_brute' => $margeBrute,
                'prix_seuil' => $prixSeuil,
                'score_roi' => $scoreROI,
                'capacite_remboursement' => $capaciteRemboursement,
                'montant_pret_max' => $montantPretMax,
                'risque_score' => $analyseRisque['score'],
                'risque_niveau' => $analyseRisque['niveau'],
            ];
        }

        return $this->render('parcelles_cultures/farmer/roi/calculator.html.twig', [
            'form' => $form->createView(),
            'roi_result' => $result
        ]);
    }

    #[Route('/analyze', name: 'analyze', methods: ['POST'])]
    public function analyze(
        \Symfony\Component\HttpFoundation\Request $request, 
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator
    ): JsonResponse
    {
        try {
            // Récupérer les données JSON
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse(['success' => false, 'error' => 'Données JSON invalides'], 400);
            }

            // 🛡️ Validation serveur (Validator non-HTML)
            // Mapper les données vers le DTO pour bénéficier des annotations @Assert
            $dto = new \App\DTO\Parcelles_Cultures\RoiDTO();
            $dto->surface_ha = isset($data['surface']) ? (float)$data['surface'] : null;
            $dto->rendement = isset($data['rendement']) ? (float)$data['rendement'] : null;
            $dto->prix_vente = isset($data['prix_vente']) ? (float)$data['prix_vente'] : null;
            $dto->cout_semences = isset($data['cout_semences']) ? (float)$data['cout_semences'] : null;
            $dto->cout_engrais = isset($data['cout_engrais']) ? (float)$data['cout_engrais'] : null;
            $dto->cout_main_oeuvre = isset($data['cout_main_oeuvre']) ? (float)$data['cout_main_oeuvre'] : null;
            $dto->cout_irrigation = isset($data['cout_irrigation']) ? (float)$data['cout_irrigation'] : null;
            $dto->cout_autres = isset($data['autres_couts']) ? (float)$data['autres_couts'] : null;
            $dto->duree_pret = isset($data['duree_pret']) ? (int)$data['duree_pret'] : 5;

            // 📍 Rechercher l'entité parcelle pour la validation
            if (isset($data['parcelle_id'])) {
                $dto->parcelle = $this->parcelleRepository->find($data['parcelle_id']);
            }

            $errors = $validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse([
                    'success' => false, 
                    'error' => 'Validation échouée : ' . implode(' | ', $errorMessages)
                ], 400);
            }

            // Appeler le moteur Python ROI
            $result = $this->pythonRoiService->analyzeROI($data);

            return new JsonResponse($result);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
}

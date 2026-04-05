<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Service\Parcelles_Cultures\IrrigationService;
use App\Service\Parcelles_Cultures\CultureService;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/irrigation', name: 'farmer_irrigation_')]
#[IsGranted('ROLE_FARMER')]
class IrrigationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CultureRepository $cultureRepository
    ): Response {
        $user = $this->getUser();
        $cultures = $cultureRepository->getActiveByAgriculteur($user->getId());

        return $this->render('parcelles_cultures/farmer/irrigation/index.html.twig', [
            'cultures' => $cultures
        ]);
    }

    #[Route('/calculator', name: 'calculator', methods: ['GET', 'POST'])]
    public function calculator(
        Request $request,
        IrrigationService $irrigationService,
        CultureService $cultureService,
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

            $joursVegetation = $cultureService->getJoursVegetation($culture);
            $result = $irrigationService->calculerIrrigation($culture, $joursVegetation);
        }

        $user = $this->getUser();
        $cultures = $cultureRepository->getActiveByAgriculteur($user->getId());

        return $this->render('parcelles_cultures/farmer/irrigation/calculator.html.twig', [
            'cultures' => $cultures,
            'result' => $result,
            'selected_culture_id' => $cultureId
        ]);
    }
}

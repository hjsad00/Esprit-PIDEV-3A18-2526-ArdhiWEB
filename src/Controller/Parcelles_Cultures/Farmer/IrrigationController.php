<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\DTO\Parcelles_Cultures\IrrigationDTO;
use App\Form\Parcelles_Cultures\Type\IrrigationFormType;
use App\Repository\Parcelles_Cultures\IrrigationRequestRepository;
use App\Service\Parcelles_Cultures\IrrigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/irrigation', name: 'farmer_irrigation_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class IrrigationController extends AbstractController
{
    public function __construct(
        private IrrigationRequestRepository $irrigationRepository,
        private IrrigationService $irrigationService
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('parcelles_cultures/farmer/irrigation/index.html.twig');
    }

    #[Route('/calculator', name: 'calculator', methods: ['GET', 'POST'])]
    public function calculator(Request $request): Response
    {
        $dto = new IrrigationDTO();
        $form = $this->createForm(IrrigationFormType::class, $dto);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $et0 = 0.0023 * ($dto->temperature_moyenne + 17.8) * sqrt($dto->temperature_max - $dto->temperature_min);
            $besoinBrut = $dto->kc * $et0;
            $besoinNet = max(0, $besoinBrut - $dto->precipitations);
            
            // Dummy surface for now since it's not linked to a specific parcelle in this form
            $surface = 1.0; 
            $volumeLitres = $besoinNet * $surface * 10000;

            $result = [
                'et0' => $et0,
                'besoin_brut' => $besoinBrut,
                'besoin_net' => $besoinNet,
                'volume_litres' => $volumeLitres,
                'volume_m3' => $volumeLitres / 1000,
                'temp' => $dto->temperature_moyenne,
                'precip' => $dto->precipitations,
                'humidite' => $dto->humidite,
            ];
        }

        return $this->render('parcelles_cultures/farmer/irrigation/calculator.html.twig', [
            'form' => $form->createView(),
            'calculation_result' => $result,
        ]);
    }
}

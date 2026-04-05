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
            // Get parcelle from request
            $parcelleId = $request->request->get('parcelle_id');
            // Get culture from request
            $cultureId = $request->request->get('culture_id');

            // For now, use dummy data for calculation
            $result = [
                'et0' => 5.2,
                'besoin_brut' => 7.8,
                'besoin_net' => 4.3,
                'volume_litres' => 43000,
            ];
        }

        return $this->render('parcelles_cultures/farmer/irrigation/calculator.html.twig', [
            'form' => $form,
            'result' => $result,
        ]);
    }
}

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
    public function calculator(): Response
    {
        return $this->render('parcelles_cultures/farmer/roi/calculator.html.twig');
    }
}

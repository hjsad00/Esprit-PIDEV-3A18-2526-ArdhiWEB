<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/agriculture', name: 'farmer_agriculture_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('parcelles_cultures/farmer/dashboard.html.twig');
    }
}

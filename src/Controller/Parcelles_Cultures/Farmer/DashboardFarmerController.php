<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Repository\Parcelles_Cultures\ParceleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/dashboard', name: 'farmer_dashboard_')]
#[IsGranted('ROLE_FARMER')]
class DashboardFarmerController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ParceleRepository $parceleRepository
    ): Response {
        $user = $this->getUser();
        $parcelles = $parceleRepository->findByAgriculteur($user->getId());

        return $this->render('parcelles_cultures/farmer/dashboard.html.twig', [
            'parcelles' => $parcelles,
            'total_parcelles' => count($parcelles)
        ]);
    }
}

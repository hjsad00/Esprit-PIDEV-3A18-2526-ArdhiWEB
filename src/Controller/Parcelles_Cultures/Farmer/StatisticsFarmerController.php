<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Repository\Parcelles_Cultures\ParceleRepository;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/stats', name: 'farmer_stats_')]
#[IsGranted('ROLE_FARMER')]
class StatisticsFarmerController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ParceleRepository $parceleRepository,
        CultureRepository $cultureRepository
    ): Response {
        $user = $this->getUser();

        $parcelles = $parceleRepository->findByAgriculteur($user->getId());
        $totalSurface = $parceleRepository->getTotalSurfaceByAgriculteur($user->getId());
        
        $stats = $cultureRepository->getStatsBySeasonForAgriculteur($user->getId());

        return $this->render('parcelles_cultures/farmer/stats.html.twig', [
            'total_parcelles' => count($parcelles),
            'total_surface' => $totalSurface,
            'stats_by_season' => $stats
        ]);
    }
}

<?php

namespace App\Controller\Parcelles_Cultures\Admin;

use App\Repository\Parcelles_Cultures\ParceleRepository;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/agriculture', name: 'admin_stats_')]
#[IsGranted('ROLE_ADMIN')]
class StatisticsAdminController extends AbstractController
{
    #[Route('/stats', name: 'index', methods: ['GET'])]
    public function index(
        ParceleRepository $parceleRepository,
        CultureRepository $cultureRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $parcelles = $parceleRepository->findAll();
        $cultures = $cultureRepository->findAll();
        
        $totalSurface = 0;
        foreach ($parcelles as $parcelle) {
            $totalSurface += $parcelle->getSurface();
        }

        $stats = $cultureRepository->getStatsBySeasonForAgriculteur(null);

        return $this->render('parcelles_cultures/admin/stats.html.twig', [
            'total_parcelles' => count($parcelles),
            'total_cultures' => count($cultures),
            'total_surface' => $totalSurface,
            'stats_by_season' => $stats
        ]);
    }
}

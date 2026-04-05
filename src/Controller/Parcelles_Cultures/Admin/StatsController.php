<?php

namespace App\Controller\Parcelles_Cultures\Admin;

use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Repository\Parcelles_Cultures\CultureRepository;
use App\Repository\UserAndDiag\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/agriculture/stats', name: 'admin_stats_')]
#[IsGranted('ROLE_ADMIN')]
class StatsController extends AbstractController
{
    public function __construct(
        private ParcelleRepository $parcelleRepository,
        private CultureRepository $cultureRepository,
        private UserRepository $userRepository
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $agriculteurs = $this->userRepository->findByRole('AGRICULTEUR');
        
        $stats = [
            'total_parcelles' => $this->parcelleRepository->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'total_cultures' => $this->cultureRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            'surface_totale' => $this->parcelleRepository->createQueryBuilder('p')
                ->select('SUM(p.surface)')
                ->getQuery()
                ->getSingleScalarResult(),
            'production_estimee' => $this->cultureRepository->createQueryBuilder('c')
                ->select('SUM(c.production_estimee)')
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        // Chart 1: Distribution des types de culture (donut)
        $typesRaw = $this->cultureRepository->createQueryBuilder('c')
            ->select('c.type_culture as type, COUNT(c.id) as count')
            ->groupBy('c.type_culture')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        // Chart 2: Production totale + rendement moyen par type (bar)
        $productionRaw = $this->cultureRepository->createQueryBuilder('c')
            ->select('c.type_culture as type, SUM(c.production_estimee) as total, AVG(c.rendement_estime) as avgRendement')
            ->groupBy('c.type_culture')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        $chartTypes = [
            'labels' => array_map(fn($r) => ucfirst($r['type']), $typesRaw),
            'counts' => array_map(fn($r) => (int)$r['count'], $typesRaw),
        ];

        $chartProduction = [
            'labels'     => array_map(fn($r) => ucfirst($r['type']), $productionRaw),
            'production' => array_map(fn($r) => round((float)$r['total'], 0), $productionRaw),
            'rendement'  => array_map(fn($r) => round((float)$r['avgRendement'], 1), $productionRaw),
        ];

        return $this->render('parcelles_cultures/admin/stats/index.html.twig', [
            'stats'           => $stats,
            'agriculteurs'    => $agriculteurs,
            'chartTypes'      => $chartTypes,
            'chartProduction' => $chartProduction,
        ]);
    }
}

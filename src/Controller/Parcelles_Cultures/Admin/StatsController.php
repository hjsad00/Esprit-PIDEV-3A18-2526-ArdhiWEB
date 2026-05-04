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
                ->enableResultCache(3600)
                ->getSingleScalarResult(),
            'total_cultures' => $this->cultureRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->getQuery()
                ->enableResultCache(3600)
                ->getSingleScalarResult(),
            'surface_totale' => $this->parcelleRepository->createQueryBuilder('p')
                ->select('SUM(p.surface)')
                ->getQuery()
                ->enableResultCache(3600)
                ->getSingleScalarResult(),
            'production_estimee' => $this->cultureRepository->createQueryBuilder('c')
                ->select('SUM(c.production_estimee)')
                ->getQuery()
                ->enableResultCache(3600)
                ->getSingleScalarResult(),
        ];

        // Chart 1: Distribution des types de culture (donut)
        $typesRaw = $this->cultureRepository->createQueryBuilder('c')
            ->select('new App\DTO\Parcelles_Cultures\ChartStatDTO(c.type_culture, COUNT(c.id))')
            ->groupBy('c.type_culture')
            ->getQuery()
            ->enableResultCache(3600)
            ->getResult();

        usort($typesRaw, fn($a, $b) => $b->count <=> $a->count);

        // Chart 2: Production totale + rendement moyen par type (bar)
        $productionRaw = $this->cultureRepository->createQueryBuilder('c')
            ->select('new App\DTO\Parcelles_Cultures\ChartStatDTO(c.type_culture, 0, SUM(c.production_estimee), AVG(c.rendement_estime))')
            ->groupBy('c.type_culture')
            ->getQuery()
            ->enableResultCache(3600)
            ->getResult();

        usort($productionRaw, fn($a, $b) => $b->total <=> $a->total);

        $chartTypes = [
            'labels' => array_map(fn($r) => ucfirst($r->type ?? 'Inconnu'), $typesRaw),
            'counts' => array_map(fn($r) => (int)$r->count, $typesRaw),
        ];

        $chartProduction = [
            'labels'     => array_map(fn($r) => ucfirst($r->type ?? 'Inconnu'), $productionRaw),
            'production' => array_map(fn($r) => round((float)$r->total, 0), $productionRaw),
            'rendement'  => array_map(fn($r) => round((float)$r->avgRendement, 1), $productionRaw),
        ];

        return $this->render('parcelles_cultures/admin/stats/index.html.twig', [
            'stats'           => $stats,
            'agriculteurs'    => $agriculteurs,
            'chartTypes'      => $chartTypes,
            'chartProduction' => $chartProduction,
        ]);
    }
}

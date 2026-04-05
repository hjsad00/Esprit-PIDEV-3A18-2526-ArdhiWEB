<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/farmer/stats', name: 'farmer_stats_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class StatsFarmerController extends AbstractController
{
    public function __construct(
        private ParcelleRepository $parcelleRepository,
        private CultureRepository $cultureRepository,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        // ── KPIs globaux ──────────────────────────────────────────────────────
        $totalParcelles = $this->parcelleRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.agriculteur = :u')->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();

        $totalSurface = $this->parcelleRepository->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.surface), 0)')
            ->where('p.agriculteur = :u')->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();

        $totalCultures = $this->cultureRepository->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->select('COUNT(c.id)')
            ->where('p.agriculteur = :u')->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();

        $totalProduction = $this->cultureRepository->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->select('COALESCE(SUM(c.production_estimee), 0)')
            ->where('p.agriculteur = :u')->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();

        // ── Chart 1 : Cultures par parcelle (bar) ────────────────────────────
        $culturesByParcelleRaw = $this->cultureRepository->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->select('p.localisation as loc, COUNT(c.id) as count, COALESCE(SUM(c.production_estimee), 0) as prod')
            ->where('p.agriculteur = :u')->setParameter('u', $user)
            ->groupBy('p.id')
            ->orderBy('prod', 'DESC')
            ->getQuery()->getResult();

        // ── Chart 2 : Répartition des types de culture (donut) ───────────────
        $typeDistribRaw = $this->cultureRepository->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->select('c.type_culture as type, COUNT(c.id) as count, COALESCE(SUM(c.production_estimee), 0) as prod')
            ->where('p.agriculteur = :u')->setParameter('u', $user)
            ->groupBy('c.type_culture')
            ->orderBy('prod', 'DESC')
            ->getQuery()->getResult();

        // ── Parcelles avec leurs cultures (table récapitulatif) ───────────────
        $parcelles = $this->parcelleRepository->createQueryBuilder('p')
            ->leftJoin('p.cultures', 'c')
            ->addSelect('c')
            ->where('p.agriculteur = :u')->setParameter('u', $user)
            ->orderBy('p.id', 'ASC')
            ->getQuery()->getResult();

        // ── Sérialisation pour Chart.js ───────────────────────────────────────
        $chartParcelles = [
            'labels'     => array_map(fn($r) => $r['loc'], $culturesByParcelleRaw),
            'cultures'   => array_map(fn($r) => (int) $r['count'], $culturesByParcelleRaw),
            'production' => array_map(fn($r) => round((float) $r['prod'], 0), $culturesByParcelleRaw),
        ];

        $chartTypes = [
            'labels'     => array_map(fn($r) => ucfirst($r['type']), $typeDistribRaw),
            'counts'     => array_map(fn($r) => (int) $r['count'], $typeDistribRaw),
            'production' => array_map(fn($r) => round((float) $r['prod'], 0), $typeDistribRaw),
        ];

        return $this->render('parcelles_cultures/farmer/stats/index.html.twig', [
            'kpi' => [
                'parcelles'  => $totalParcelles,
                'surface'    => round((float) $totalSurface, 1),
                'cultures'   => $totalCultures,
                'production' => round((float) $totalProduction, 0),
            ],
            'parcelles'      => $parcelles,
            'chartParcelles' => $chartParcelles,
            'chartTypes'     => $chartTypes,
        ]);
    }
}

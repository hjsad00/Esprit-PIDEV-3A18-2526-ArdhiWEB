<?php

namespace App\Controller\Parcelles_Cultures\Admin;

use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Repository\Parcelles_Cultures\CultureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/agriculture', name: 'admin_agriculture_')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    public function __construct(
        private ParcelleRepository $parcelleRepo,
        private CultureRepository $cultureRepo,
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // Récupérer les statistiques principales
        $parcellesCount = $this->parcelleRepo->count([]);
        $culturesCount = $this->cultureRepo->count([]);
        $parcellesActives = $this->parcelleRepo->count(['statut' => 'actif']);
        $parcellesInactives = $this->parcelleRepo->count(['statut' => 'inactif']);
        
        // Calculer la surface totale
        $qb = $this->em->createQueryBuilder();
        $surfaceTotale = $qb
            ->select('SUM(p.surface) as totalSurface')
            ->from('App\Entity\Parcelles_Cultures\Parcelle', 'p')
            ->getQuery()
            ->enableResultCache(3600)
            ->getOneOrNullResult();
        
        // Calculer la production totale estimée
        $qb2 = $this->em->createQueryBuilder();
        $productionTotale = $qb2
            ->select('SUM(c.production_estimee) as totalProduction')
            ->from('App\Entity\Parcelles_Cultures\Culture', 'c')
            ->getQuery()
            ->enableResultCache(3600)
            ->getOneOrNullResult();
        
        // Récupérer les dernières parcelles (5 dernières)
        $recentParcelles = $this->parcelleRepo->findBy(
            [],
            ['created_at' => 'DESC'],
            5
        );
        
        // Récupérer les dernières cultures (5 dernières)
        $recentCultures = $this->cultureRepo->findBy(
            [],
            ['created_at' => 'DESC'],
            5
        );
        
        // Données pour le graphique de distribution des types de sol
        $soilTypeQb = $this->em->createQueryBuilder()
            ->select('new App\DTO\Parcelles_Cultures\ChartStatDTO(p.type_sol, COUNT(p.id))')
            ->from('App\Entity\Parcelles_Cultures\Parcelle', 'p')
            ->groupBy('p.type_sol');
        
        $soilTypeData = $soilTypeQb->getQuery()->enableResultCache(3600)->getResult();
        $soilTypeLabels = !empty($soilTypeData) ? array_map(fn($item) => $item->type ?? 'N/A', $soilTypeData) : [];
        $soilTypeCounts = !empty($soilTypeData) ? array_map(fn($item) => (int)$item->count, $soilTypeData) : [];
        
        // Données pour le graphique de distribution des types de culture
        $cultureTypeQb = $this->em->createQueryBuilder()
            ->select('new App\DTO\Parcelles_Cultures\ChartStatDTO(c.type_culture, COUNT(c.id))')
            ->from('App\Entity\Parcelles_Cultures\Culture', 'c')
            ->groupBy('c.type_culture');
        
        $cultureTypeData = $cultureTypeQb->getQuery()->enableResultCache(3600)->getResult();
        $cultureTypeLabels = !empty($cultureTypeData) ? array_map(fn($item) => $item->type ?? 'N/A', $cultureTypeData) : [];
        $cultureTypeCounts = !empty($cultureTypeData) ? array_map(fn($item) => (int)$item->count, $cultureTypeData) : [];

        return $this->render('parcelles_cultures/admin/dashboard.html.twig', [
            'stats' => [
                'parcellesCount' => $parcellesCount,
                'culturesCount' => $culturesCount,
                'parcellesActives' => $parcellesActives,
                'parcellesInactives' => $parcellesInactives,
                'surfaceTotale' => (float)($surfaceTotale['totalSurface'] ?? 0),
                'productionTotale' => (float)($productionTotale['totalProduction'] ?? 0),
            ],
            'recentParcelles' => $recentParcelles,
            'recentCultures' => $recentCultures,
            'soilTypeLabels' => $soilTypeLabels,
            'soilTypeData' => $soilTypeCounts,
            'cultureTypeLabels' => $cultureTypeLabels,
            'cultureTypeData' => $cultureTypeCounts,
        ]);
    }
}

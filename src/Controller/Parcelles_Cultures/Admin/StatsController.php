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

        return $this->render('parcelles_cultures/admin/stats/index.html.twig', [
            'stats' => $stats,
            'agriculteurs' => $agriculteurs,
        ]);
    }
}

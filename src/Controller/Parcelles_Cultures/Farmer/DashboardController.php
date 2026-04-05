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
    public function index(
        \App\Repository\Parcelles_Cultures\ParcelleRepository $parcelleRepo,
        \App\Repository\Parcelles_Cultures\CultureRepository $cultureRepo
    ): Response
    {
        $user = $this->getUser();
        $stats = [
            'parcelles' => $parcelleRepo->count(['agriculteur' => $user]),
            'surface'   => $parcelleRepo->createQueryBuilder('p')
                ->select('SUM(p.surface)')
                ->where('p.agriculteur = :u')->setParameter('u', $user)
                ->getQuery()->getSingleScalarResult(),
            'cultures'  => $cultureRepo->createQueryBuilder('c')
                ->join('c.parcelle', 'p')
                ->select('COUNT(c.id)')
                ->where('p.agriculteur = :u')->setParameter('u', $user)
                ->getQuery()->getSingleScalarResult(),
        ];

        return $this->render('parcelles_cultures/farmer/dashboard.html.twig', [
            'stats' => $stats
        ]);
    }
}

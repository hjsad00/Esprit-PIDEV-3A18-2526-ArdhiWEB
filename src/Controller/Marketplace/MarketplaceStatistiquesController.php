<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\DetailsCommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MarketplaceStatistiquesController extends AbstractController
{
    #[Route('/marketplace/mes-statistiques', name: 'app_marketplace_statistiques')]
    public function statistiques(CommandeRepository $commandeRepo, DetailsCommandeRepository $detailsRepo): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // 1. KPIs de base
        $buyerStats = $commandeRepo->getStatsForBuyer($user);
        $sellerStats = $commandeRepo->getStatsForSeller($user);

        // 2. Données pour la courbe (Achats vs Ventes par mois sur les 6 derniers mois)
        $months = [];
        $purchaseData = [];
        $salesData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = new \DateTime("first day of -$i month");
            $monthKey = $date->format('Y-m');
            $months[] = $date->format('M Y');
            
            // Achats du mois
            $start = (clone $date)->setTime(0,0,0);
            $end = (clone $date)->modify('last day of this month')->setTime(23,59,59);
            
            $purchaseResult = $commandeRepo->createQueryBuilder('c')
                ->select('COALESCE(SUM(c.total), 0)')
                ->where('c.user = :user')
                ->andWhere('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('c.etat != :annulee')
                ->setParameter('user', $user)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->setParameter('annulee', 'annulee')
                ->getQuery()
                ->getSingleScalarResult();
            
            $purchaseData[] = (float) $purchaseResult;

            // Ventes du mois (somme des produits du vendeur dans toutes les commandes)
            $salesResult = $commandeRepo->createQueryBuilder('c')
                ->select('COALESCE(SUM(d.prixUnitaire * d.quantite), 0)')
                ->innerJoin('c.details', 'd')
                ->innerJoin('d.produit', 'p')
                ->where('p.user = :user')
                ->andWhere('c.dateCommande BETWEEN :start AND :end')
                ->andWhere('c.etat != :annulee')
                ->setParameter('user', $user)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->setParameter('annulee', 'annulee')
                ->getQuery()
                ->getSingleScalarResult();
                
            $salesData[] = (float) $salesResult;
        }

        // 3. Répartition des ventes par produit (Top 5)
        $topProductsResult = $detailsRepo->createQueryBuilder('d')
            ->select('p.nom, SUM(d.quantite) as totalQty')
            ->innerJoin('d.produit', 'p')
            ->where('p.user = :user')
            ->groupBy('p.id')
            ->orderBy('totalQty', 'DESC')
            ->setMaxResults(5)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $productLabels = [];
        $productValues = [];
        foreach ($topProductsResult as $row) {
            $productLabels[] = $row['nom'];
            $productValues[] = (int) $row['totalQty'];
        }

        return $this->render('Marketplace/statistiques.html.twig', [
            'buyerStats' => $buyerStats,
            'sellerStats' => $sellerStats,
            'chartLabels' => $months,
            'purchaseChartData' => $purchaseData,
            'salesChartData' => $salesData,
            'productLabels' => $productLabels,
            'productValues' => $productValues
        ]);
    }
}

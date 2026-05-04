<?php

namespace App\Controller\Marketplace;

use CMEN\GoogleChartsBundle\GoogleCharts\Charts\LineChart;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\PieChart;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\DetailsCommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

        $evolutionChart = new LineChart();
        $evolutionRows = [['Mois', 'Ventes (Commandes recues)', 'Achats (Mes commandes)']];
        foreach ($months as $index => $monthLabel) {
            $evolutionRows[] = [$monthLabel, $salesData[$index], $purchaseData[$index]];
        }
        $evolutionChart->getData()->setArrayToDataTable($evolutionRows);
        $evolutionChart->getOptions()->setHeight(320);
        $evolutionChart->getOptions()->setBackgroundColor('transparent');
        $evolutionChart->getOptions()->setCurveType('function');
        $evolutionChart->getOptions()->setLineWidth(4);
        $evolutionChart->getOptions()->setPointSize(6);
        $evolutionChart->getOptions()->setColors(['#116530', '#dc3545']);
        $evolutionChart->getOptions()->getChartArea()->setLeft('9%');
        $evolutionChart->getOptions()->getChartArea()->setTop('8%');
        $evolutionChart->getOptions()->getChartArea()->setWidth('86%');
        $evolutionChart->getOptions()->getChartArea()->setHeight('70%');
        $evolutionChart->getOptions()->getLegend()->setPosition('bottom');
        $evolutionChart->getOptions()->getLegend()->setAlignment('center');
        $evolutionChart->getOptions()->getLegend()->getTextStyle()->setColor('#334155');
        $evolutionChart->getOptions()->getLegend()->getTextStyle()->setFontSize(12);
        $evolutionChart->getOptions()->getLegend()->getTextStyle()->setFontName('Inter');
        $evolutionChart->getOptions()->getHAxis()->setTitle('Mois');
        $evolutionChart->getOptions()->getHAxis()->getGridlines()->setColor('transparent');
        $evolutionChart->getOptions()->getHAxis()->getTextStyle()->setColor('#64748b');
        $evolutionChart->getOptions()->getHAxis()->getTextStyle()->setFontSize(11);
        $evolutionChart->getOptions()->getHAxis()->getTitleTextStyle()->setColor('#334155');
        $evolutionChart->getOptions()->getHAxis()->getTitleTextStyle()->setBold(true);
        $evolutionChart->getOptions()->getVAxis()->setTitle('Montant (DT)');
        $evolutionChart->getOptions()->getVAxis()->setMinValue(0);
        $evolutionChart->getOptions()->getVAxis()->getGridlines()->setColor('#e2e8f0');
        $evolutionChart->getOptions()->getVAxis()->getTextStyle()->setColor('#64748b');
        $evolutionChart->getOptions()->getVAxis()->getTextStyle()->setFontSize(11);
        $evolutionChart->getOptions()->getVAxis()->getTitleTextStyle()->setColor('#334155');
        $evolutionChart->getOptions()->getVAxis()->getTitleTextStyle()->setBold(true);
        $evolutionChart->getOptions()->getVAxis()->setTicks(
            $this->buildDtTicks(array_merge($salesData, $purchaseData))
        );

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

        $productChart = new PieChart();
        $productRows = [['Produit', 'Quantite vendue']];
        foreach ($topProductsResult as $row) {
            $productRows[] = [$row['nom'], (int) $row['totalQty']];
        }

        $hasProductData = count($productRows) > 1;
        if ($hasProductData) {
            $productChart->getData()->setArrayToDataTable($productRows);
            $productChart->getOptions()->setHeight(320);
            $productChart->getOptions()->setBackgroundColor('transparent');
            $productChart->getOptions()->getChartArea()->setLeft('6%');
            $productChart->getOptions()->getChartArea()->setTop('8%');
            $productChart->getOptions()->getChartArea()->setWidth('88%');
            $productChart->getOptions()->getChartArea()->setHeight('72%');
            $productChart->getOptions()->setPieHole(0.55);
            $productChart->getOptions()->setPieSliceText('percentage');
            $productChart->getOptions()->setColors(['#116530', '#1a8a44', '#2ec165', '#9ae6b4', '#c6f6d5']);
            $productChart->getOptions()->getLegend()->setPosition('bottom');
            $productChart->getOptions()->getLegend()->setAlignment('center');
            $productChart->getOptions()->getLegend()->getTextStyle()->setColor('#334155');
            $productChart->getOptions()->getLegend()->getTextStyle()->setFontSize(12);
            $productChart->getOptions()->getLegend()->getTextStyle()->setFontName('Inter');
            $productChart->getOptions()->getPieSliceTextStyle()->setColor('#0f172a');
            $productChart->getOptions()->getPieSliceTextStyle()->setFontSize(11);
            $productChart->getOptions()->getPieSliceTextStyle()->setFontName('Inter');
        }

        return $this->render('Marketplace/statistiques.html.twig', [
            'buyerStats' => $buyerStats,
            'sellerStats' => $sellerStats,
            'evolutionChart' => $evolutionChart,
            'productChart' => $productChart,
            'hasProductData' => $hasProductData,
        ]);
    }

    /**
     * @param float[] $values
     * @return array<int, array{v: float, f: string}>
     */
    private function buildDtTicks(array $values): array
    {
        $maxValue = max(1.0, (float) max($values ?: [0.0]));
        $step = $maxValue / 4;
        $ticks = [];

        for ($i = 0; $i <= 4; $i++) {
            $value = round($step * $i, 2);
            $ticks[] = [
                'v' => $value,
                'f' => number_format($value, 2, ',', ' ') . ' DT',
            ];
        }

        return $ticks;
    }
}

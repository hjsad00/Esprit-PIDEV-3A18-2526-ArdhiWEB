<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\AvisRepository;
use App\Repository\Marketplace\ProduitsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompareController extends AbstractController
{
    #[Route('/marketplace/comparer', name: 'app_marketplace_compare', methods: ['GET'])]
    public function compare(
        Request $request,
        ProduitsRepository $produitsRepository,
        AvisRepository $avisRepository
    ): Response {
        $xId = $request->query->getInt('x', 0);
        $yId = $request->query->getInt('y', 0);

        $error = null;

        if ($xId <= 0 || $yId <= 0) {
            $error = 'Selectionnez deux produits pour lancer la comparaison.';
        } elseif ($xId === $yId) {
            $error = 'Veuillez choisir deux produits differents.';
        }

        $produitX = null;
        $produitY = null;

        if ($error === null) {
            $produitX = $produitsRepository->findOneBy([
                'id' => $xId,
                'visible' => true,
                'visibleAdmin' => true,
            ]);

            $produitY = $produitsRepository->findOneBy([
                'id' => $yId,
                'visible' => true,
                'visibleAdmin' => true,
            ]);

            if (!$produitX || !$produitY) {
                $error = 'Un ou plusieurs produits selectionnes ne sont plus disponibles.';
            }
        }

        if ($error === null && $produitX && $produitY) {
            $stats = $avisRepository->getStatsForProduits([$produitX, $produitY]);

            $xStats = $stats[$produitX->getId()] ?? ['avg' => 0.0, 'count' => 0];
            $yStats = $stats[$produitY->getId()] ?? ['avg' => 0.0, 'count' => 0];

            $produitX
                ->setAverageRating((float) $xStats['avg'])
                ->setReviewsCount((int) $xStats['count']);

            $produitY
                ->setAverageRating((float) $yStats['avg'])
                ->setReviewsCount((int) $yStats['count']);
        }

        $winners = [
            'price' => null,
            'stock' => null,
            'rating' => null,
            'categoryMatch' => false,
        ];

        if ($error === null && $produitX && $produitY) {
            $xFinalPrice = $produitX->getPrixFinal();
            $yFinalPrice = $produitY->getPrixFinal();

            if ($xFinalPrice < $yFinalPrice) {
                $winners['price'] = 'x';
            } elseif ($yFinalPrice < $xFinalPrice) {
                $winners['price'] = 'y';
            }

            $xStock = (int) $produitX->getQuantiteStock();
            $yStock = (int) $produitY->getQuantiteStock();
            if ($xStock > $yStock) {
                $winners['stock'] = 'x';
            } elseif ($yStock > $xStock) {
                $winners['stock'] = 'y';
            }

            $xRating = (float) $produitX->getAverageRating();
            $yRating = (float) $produitY->getAverageRating();
            if ($xRating > $yRating) {
                $winners['rating'] = 'x';
            } elseif ($yRating > $xRating) {
                $winners['rating'] = 'y';
            }

            $xCategory = mb_strtolower(trim((string) $produitX->getCategorie()));
            $yCategory = mb_strtolower(trim((string) $produitY->getCategorie()));
            $winners['categoryMatch'] = $xCategory !== '' && $xCategory === $yCategory;
        }

        return $this->render('Marketplace/comparer.html.twig', [
            'produitX' => $produitX,
            'produitY' => $produitY,
            'error' => $error,
            'winners' => $winners,
        ]);
    }
}

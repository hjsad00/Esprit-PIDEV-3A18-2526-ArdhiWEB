<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Avis;
use App\Entity\Marketplace\Produits;
use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\AvisRepository;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\NotifMarketRepository;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/marketplace/avis', name: 'app_marketplace_avis_')]
class AvisController extends AbstractController
{
    #[Route('/{produitId}', name: 'list', methods: ['GET'], requirements: ['produitId' => '\\d+'])]
    public function list(
        int $produitId,
        ProduitsRepository $produitsRepository,
        AvisRepository $avisRepository
    ): JsonResponse {
        $produit = $produitsRepository->find($produitId);
        if (!$produit instanceof Produits) {
            return $this->json(['success' => false, 'message' => 'Produit introuvable.'], 404);
        }

        $avis = $avisRepository->findByProduitWithUser($produit);
        $stats = $avisRepository->getStatsForProduits([$produit]);
        $produitStats = $stats[$produit->getId()] ?? ['avg' => 0.0, 'count' => 0];

        $reviews = array_map(static function (Avis $review): array {
            $user = $review->getUser();
            $fullName = trim((string) ($user?->getPrenom() ?? '') . ' ' . (string) ($user?->getNom() ?? ''));

            return [
                'id' => $review->getId(),
                'note' => $review->getNote(),
                'commentaire' => $review->getCommentaire(),
                'dateAvis' => $review->getDateAvis()->format('d/m/Y'),
                'isVerifiedBuyer' => $review->isVerifiedBuyer(),
                'user' => [
                    'id' => $user?->getId(),
                    'name' => $fullName !== '' ? $fullName : 'Utilisateur',
                ],
            ];
        }, $avis);

        return $this->json([
            'success' => true,
            'averageRating' => (float) $produitStats['avg'],
            'reviewsCount' => (int) $produitStats['count'],
            'reviews' => $reviews,
        ]);
    }

    #[Route('/{produitId}/add', name: 'add', methods: ['POST'], requirements: ['produitId' => '\\d+'])]
    public function add(
        int $produitId,
        Request $request,
        ProduitsRepository $produitsRepository,
        CommandeRepository $commandeRepository,
        NotifMarketRepository $notifMarketRepository,
        AvisRepository $avisRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Authentification requise.'], 401);
        }

        $produit = $produitsRepository->find($produitId);
        if (!$produit instanceof Produits) {
            return $this->json(['success' => false, 'message' => 'Produit introuvable.'], 404);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Payload JSON invalide.'], 400);
        }

        $note = isset($payload['note']) ? (int) $payload['note'] : 0;
        $commentaire = isset($payload['commentaire']) ? trim((string) $payload['commentaire']) : null;

        if ($note < 1 || $note > 5) {
            return $this->json(['success' => false, 'message' => 'La note doit être comprise entre 1 et 5.'], 422);
        }

        $isVerifiedBuyer = $commandeRepository->hasUserBoughtProduct($user, $produit);

        $avis = new Avis();
        $avis
            ->setUser($user)
            ->setProduit($produit)
            ->setNote($note)
            ->setCommentaire($commentaire !== '' ? $commentaire : null)
            ->setIsVerifiedBuyer($isVerifiedBuyer);

        $fullName = trim((string) ($user->getPrenom() ?? '') . ' ' . (string) ($user->getNom() ?? ''));

        $entityManager->persist($avis);
        $entityManager->flush();

        $seller = $produit->getUser();
        if ($seller instanceof User) {
            $sellerId = $seller->getId();
        } else {
            $sellerId = null;
        }

        if ($sellerId !== null && $sellerId !== $user->getId()) {
            $notifMarketRepository->notifierNouvelAvis(
                $sellerId,
                (int) $produit->getId(),
                $produit->getNom(),
                $fullName !== '' ? $fullName : 'Utilisateur',
                $note
            );
        }

        $stats = $avisRepository->getStatsForProduits([$produit]);
        $produitStats = $stats[$produit->getId()] ?? ['avg' => 0.0, 'count' => 0];


        return $this->json([
            'success' => true,
            'message' => 'Votre avis a ete enregistre avec succes.',
            'averageRating' => (float) $produitStats['avg'],
            'reviewsCount' => (int) $produitStats['count'],
            'review' => [
                'id' => $avis->getId(),
                'note' => $avis->getNote(),
                'commentaire' => $avis->getCommentaire(),
                'dateAvis' => $avis->getDateAvis()->format('d/m/Y'),
                'isVerifiedBuyer' => $avis->isVerifiedBuyer(),
                'user' => [
                    'id' => $user->getId(),
                    'name' => $fullName !== '' ? $fullName : 'Utilisateur',
                ],
            ],
        ], 201);
    }
}

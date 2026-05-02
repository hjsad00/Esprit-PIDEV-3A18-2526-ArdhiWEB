<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Panier;
use App\Entity\Marketplace\PanierProduit;
use App\Entity\Marketplace\Produits;
use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\PanierProduitRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\Marketplace\AvisRepository;
use App\Service\Marketplace\OllamaMarketplaceIntentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/marketplace/chatbot')]
class MarketplaceChatbotController extends AbstractController
{
    #[Route('/send', name: 'app_marketplace_chatbot_send', methods: ['POST'])]
    public function send(
        Request $request,
        OllamaMarketplaceIntentService $ollamaService,
        ProduitsRepository $produitsRepository,
        PanierRepository $panierRepository,
        PanierProduitRepository $panierProduitRepository,
        AvisRepository $avisRepository,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            return $this->json([
                'success' => false,
                'intent' => 'hors_sujet',
                'message' => 'Bonjour ! Comment puis-je vous aider aujourd\'hui ?',
            ], 400);
        }

        $intent = $ollamaService->analyser($message);

        return match ($intent['intention']) {
            'achat' => $this->handleAchat($intent, $produitsRepository, $panierRepository, $panierProduitRepository, $avisRepository),
            'supprimer_produit' => $this->handleSupprimerProduit($intent, $panierRepository, $panierProduitRepository),
            'vider_panier' => $this->handleViderPanier($panierRepository),
            'disponibilite' => $this->handleDisponibilite($intent, $produitsRepository, $avisRepository),
            'filtrer' => $this->handleFiltrer($intent),
            'salutation' => $this->json([
                'success' => true,
                'intent' => 'salutation',
                'message' => "Bonjour ! Je suis votre assistant. Que puis-je faire pour vous aujourd'hui ?",
            ]),
            'remerciement' => $this->json([
                'success' => true,
                'intent' => 'remerciement',
                'message' => "Pas de souci ! N'hésitez pas si vous avez d'autres questions. 😊",
            ]),
            default => $this->json([
                'success' => true,
                'intent' => 'hors_sujet',
                'message' => "Je suis là pour vous aider ! Je peux ajouter ou retirer des articles de votre panier, vérifier nos stocks ou vous aider à trouver les meilleurs produits selon vos critères.",
            ]),
        };
    }

    /**
     * @param array<string,mixed> $intent
     */
    private function handleAchat(
        array $intent,
        ProduitsRepository $produitsRepository,
        PanierRepository $panierRepository,
        PanierProduitRepository $panierProduitRepository,
        AvisRepository $avisRepository,
    ): JsonResponse {
        $user = $this->getMarketplaceUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'intent' => 'achat',
                'requireLogin' => true,
                'message' => 'Veuillez vous connecter à votre compte pour que je puisse ajouter ces produits à votre panier.',
            ]);
        }

        $demandes = $intent['produits'] ?? [];
        if (!is_array($demandes) || $demandes === []) {
            return $this->json([
                'success' => false,
                'intent' => 'achat',
                'message' => 'Quels produits souhaitez-vous que j\'ajoute à votre panier ?',
                'cart' => $this->buildCartPayload($panierRepository->findPanierActif($user)),
            ]);
        }

        $panier = $panierRepository->getOrCreatePanier($user);
        $ajoutes = [];
        $introuvables = [];
        $stocksLimites = [];

        foreach ($demandes as $demande) {
            if (!is_array($demande)) {
                continue;
            }

            $nomDemande = trim((string) ($demande['nom'] ?? ''));
                $quantiteDemandee = (int) ($demande['quantite'] ?? 1);

            if ($nomDemande === '') {
                continue;
            }

            $produit = $this->resolveProductByName($nomDemande, $produitsRepository, $avisRepository, $user, $intent['critere'] ?? null);
            if (!$produit) {
                $introuvables[] = $nomDemande;
                continue;
            }

            $ligneExistante = $panierProduitRepository->findLigne($panier, $produit);
            $quantiteDejaDansPanier = $ligneExistante?->getQuantite() ?? 0;
            $stockRestant = max(0, (int) $produit->getQuantiteStock() - $quantiteDejaDansPanier);

            if ($stockRestant <= 0) {
                $stocksLimites[] = sprintf('%s (stock atteint)', $produit->getNom());
                continue;
            }

            $quantiteAjoutee = min($quantiteDemandee, $stockRestant);
            if ($quantiteAjoutee < 1) {
                continue;
            }

            $panierProduitRepository->ajouterOuIncrementer($panier, $produit, $quantiteAjoutee);
            $ajoutes[] = sprintf('%d x %s', $quantiteAjoutee, $produit->getNom());

            if ($quantiteAjoutee < $quantiteDemandee) {
                $stocksLimites[] = sprintf('%s (stock limite a %d)', $produit->getNom(), $quantiteAjoutee);
            }
        }

        $panierFrais = $panierRepository->findPanierWithProduits((int) $panier->getId()) ?? $panier;
        $messages = [];

        if ($ajoutes !== []) {
            $messages[] = 'C\'est fait ! J\'ai ajouté à votre panier : ' . implode(', ', $ajoutes) . '.';
        }
        if ($introuvables !== []) {
            $messages[] = 'Je n\'ai malheureusement pas pu trouver : ' . implode(', ', array_unique($introuvables)) . '.';
        }
        if ($stocksLimites !== []) {
            $messages[] = 'Attention, le stock est limité pour : ' . implode(', ', array_unique($stocksLimites)) . '.';
        }
        if ($messages === []) {
            $messages[] = 'Désolé, je n\'ai pas pu ajouter de produits à votre panier.';
        }

        return $this->json([
            'success' => $ajoutes !== [],
            'intent' => 'achat',
            'message' => implode(' ', $messages),
            'cart' => $this->buildCartPayload($panierFrais),
        ]);
    }

    /**
     * @param array<string,mixed> $intent
     */
    private function handleSupprimerProduit(
        array $intent,
        PanierRepository $panierRepository,
        PanierProduitRepository $panierProduitRepository,
    ): JsonResponse {
        $user = $this->getMarketplaceUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'intent' => 'supprimer_produit',
                'requireLogin' => true,
                'message' => 'Pour que je puisse modifier votre panier, merci de vous connecter à votre compte.',
            ]);
        }

        $demandes = $intent['produits'] ?? [];
        if (!is_array($demandes) || $demandes === []) {
            return $this->json([
                'success' => false,
                'intent' => 'supprimer_produit',
                'message' => 'Pouvez-vous m\'indiquer le produit que vous souhaitez retirer de votre panier ?',
                'cart' => $this->buildCartPayload($panierRepository->findPanierActif($user)),
            ]);
        }

        $panier = $panierRepository->findPanierActif($user);
        if (!$panier) {
            return $this->json([
                'success' => false,
                'intent' => 'supprimer_produit',
                'message' => 'Votre panier est déjà vide, vous pouvez continuer vos achats.',
                'cart' => $this->buildCartPayload(null),
            ]);
        }

        $lignes = $panierProduitRepository->findLignesByPanier($panier);
        if ($lignes === []) {
            return $this->json([
                'success' => false,
                'intent' => 'supprimer_produit',
                'message' => 'Votre panier est déjà vide, vous pouvez continuer vos achats.',
                'cart' => $this->buildCartPayload($panier),
            ]);
        }

        $supprimes = [];
        $nonTrouves = [];

        foreach ($demandes as $demande) {
            if (!is_array($demande)) {
                continue;
            }

            $nomDemande = trim((string) ($demande['nom'] ?? ''));
            if ($nomDemande === '') {
                continue;
            }

            $ligne = $this->resolveCartLineByName($nomDemande, $lignes);
            if (!$ligne || !$ligne->getProduit()) {
                $nonTrouves[] = $nomDemande;
                continue;
            }

            $produit = $ligne->getProduit();
            $panierProduitRepository->supprimerLigne($panier, $produit);
            $supprimes[] = $produit->getNom();

            $lignes = array_values(array_filter(
                $lignes,
                static fn (PanierProduit $currentLigne): bool => $currentLigne->getProduit()?->getId() !== $produit->getId()
            ));
        }

        $panierFrais = $panierRepository->findPanierWithProduits((int) $panier->getId()) ?? $panier;
        $messages = [];

        if ($supprimes !== []) {
            $messages[] = 'C\'est noté ! J\'ai retiré de votre panier : ' . implode(', ', array_unique($supprimes)) . '.';
        }
        if ($nonTrouves !== []) {
            $messages[] = 'Ce produit ne semble pas être dans votre panier : ' . implode(', ', array_unique($nonTrouves)) . '.';
        }
        if ($messages === []) {
            $messages[] = 'Désolé, je n\'ai pas trouvé ce produit dans votre panier.';
        }

        return $this->json([
            'success' => $supprimes !== [],
            'intent' => 'supprimer_produit',
            'message' => implode(' ', $messages),
            'cart' => $this->buildCartPayload($panierFrais),
        ]);
    }

    private function handleViderPanier(PanierRepository $panierRepository): JsonResponse
    {
        $user = $this->getMarketplaceUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'intent' => 'vider_panier',
                'requireLogin' => true,
                'message' => 'Veuillez vous connecter pour que je puisse vider votre panier.',
            ]);
        }

        $panierRepository->viderPanierUser($user);

        return $this->json([
            'success' => true,
            'intent' => 'vider_panier',
            'message' => 'Votre panier a été complètement vidé.',
            'cart' => $this->buildCartPayload(null),
        ]);
    }

    /**
     * @param array<string,mixed> $intent
     */
    private function handleDisponibilite(array $intent, ProduitsRepository $produitsRepository, AvisRepository $avisRepository): JsonResponse
    {
        $user = $this->getMarketplaceUser();
        $demandes = $intent['produits'] ?? [];

        if (!is_array($demandes) || $demandes === []) {
            return $this->json([
                'success' => false,
                'intent' => 'disponibilite',
                'message' => 'De quel produit souhaitez-vous vérifier la disponibilité ?',
            ]);
        }

        $details = [];
        $introuvables = [];

        foreach ($demandes as $demande) {
            if (!is_array($demande)) {
                continue;
            }

            $nomDemande = trim((string) ($demande['nom'] ?? ''));
            if ($nomDemande === '') {
                continue;
            }

            $produit = $this->resolveProductByName($nomDemande, $produitsRepository, $avisRepository, $user);
            if (!$produit) {
                $introuvables[] = $nomDemande;
                continue;
            }

            $details[] = sprintf(
                '%s: %d en stock, %.2f DT',
                $produit->getNom(),
                (int) $produit->getQuantiteStock(),
                $produit->getPrixFinal()
            );
        }

        $messages = [];
        if ($details !== []) {
            $messages[] = 'Bonne nouvelle ! ' . implode(' | ', $details) . '.';
        }
        if ($introuvables !== []) {
            $messages[] = 'Je n\'ai pas pu trouver : ' . implode(', ', array_unique($introuvables)) . '.';
        }

        if ($messages === []) {
            $messages[] = 'Je ne trouve pas ce produit pour le moment.';
        }

        return $this->json([
            'success' => $details !== [],
            'intent' => 'disponibilite',
            'message' => implode(' ', $messages),
        ]);
    }

    /**
     * @param array<string,mixed> $intent
     */
    private function handleFiltrer(array $intent): JsonResponse
    {
        $tri = $intent['critere'] ?? null;
        if (!in_array($tri, ['prix_asc', 'prix_desc'], true)) {
            $tri = '';
        }

        $filters = [
            'nom' => (string) ($intent['recherche'] ?? ''),
            'categorie' => (string) ($intent['categorie'] ?? ''),
            'prix_min' => isset($intent['prixMin']) ? (string) $intent['prixMin'] : '',
            'prix_max' => isset($intent['prixMax']) ? (string) $intent['prixMax'] : '',
            'tri' => $tri,
        ];

        $summary = [];
        if ($filters['nom'] !== '') {
            $summary[] = 'recherche=' . $filters['nom'];
        }
        if ($filters['categorie'] !== '') {
            $summary[] = 'categorie=' . $filters['categorie'];
        }
        if ($filters['prix_min'] !== '' || $filters['prix_max'] !== '') {
            $summary[] = 'prix=' . ($filters['prix_min'] !== '' ? $filters['prix_min'] : '0') . ' a ' . ($filters['prix_max'] !== '' ? $filters['prix_max'] : 'max');
        }
        if ($filters['tri'] !== '') {
            $summary[] = 'tri=' . $filters['tri'];
        }

        if ($summary === []) {
            $summary[] = 'J\'ai réinitialisé les filtres pour afficher tout le catalogue';
            $mainMessage = $summary[0] . '.';
        } else {
            $mainMessage = 'Je viens d\'appliquer vos filtres pour : ' . implode(', ', $summary) . '.';
        }

        return $this->json([
            'success' => true,
            'intent' => 'filtrer',
            'message' => $mainMessage,
            'filters' => $filters,
        ]);
    }

    private function getMarketplaceUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function resolveProductByName(string $requestedName, ProduitsRepository $produitsRepository, AvisRepository $avisRepository, ?User $user, ?string $critere = null): ?Produits
    {
        $requestedName = trim($requestedName);
        if ($requestedName === '') {
            return null;
        }

        $candidates = $produitsRepository->searchByKeyword($requestedName);
        if ($user) {
            $candidates = array_values(array_filter(
                $candidates,
                static fn (Produits $produit): bool => $produit->getUser()?->getId() !== $user->getId()
            ));
        }

        if ($candidates === []) {
            return null;
        }

        // Hydrate ratings if sorting by reviews is requested
        if ($critere === 'avis_asc' || $critere === 'avis_desc') {
            $reviewsStats = $avisRepository->getStatsForProduits($candidates);
            foreach ($candidates as $produit) {
                $stats = $reviewsStats[$produit->getId()] ?? ['avg' => 0.0, 'count' => 0];
                $produit->setAverageRating((float) $stats['avg'])
                        ->setReviewsCount((int) $stats['count']);
            }
        }

        $needle = $this->normalizeText($requestedName);

        // Collecter les candidats qui matchent par nom (exact puis partiel)
        $exactMatches = [];
        $partialMatches = [];

        foreach ($candidates as $produit) {
            $normalizedName = $this->normalizeText((string) $produit->getNom());
            if ($normalizedName === $needle) {
                $exactMatches[] = $produit;
            } elseif (str_contains($normalizedName, $needle) || str_contains($needle, $normalizedName)) {
                $partialMatches[] = $produit;
            }
        }

        // Fusionner : exact d'abord, puis partiel, puis tous les candidats en dernier
        $pool = array_merge($exactMatches, $partialMatches);
        if ($pool === []) {
            $pool = $candidates;
        }

        // Trier par avis si demande
        if ($critere === 'avis_asc' || $critere === 'avis_desc') {
            usort($pool, static function (Produits $a, Produits $b) use ($critere): int {
                $avisA = $a->getAverageRating();
                $avisB = $b->getAverageRating();
                // Si egalite sur la note, departager en favorisant le nombre d'avis
                if ($avisA == $avisB) {
                    $countA = $a->getReviewsCount();
                    $countB = $b->getReviewsCount();
                    return $critere === 'avis_asc'
                        ? $countA <=> $countB
                        : $countB <=> $countA;
                }
                return $critere === 'avis_asc'
                    ? $avisA <=> $avisB
                    : $avisB <=> $avisA;
            });
        }

        // Trier par prix si un critere est fourni (prix_asc = moins cher, prix_desc = plus cher)
        if ($critere === 'prix_asc' || $critere === 'prix_desc') {
            usort($pool, static function (Produits $a, Produits $b) use ($critere): int {
                $prixA = $a->getPrixFinal();
                $prixB = $b->getPrixFinal();
                return $critere === 'prix_asc'
                    ? $prixA <=> $prixB
                    : $prixB <=> $prixA;
            });
        }

        return $pool[0];
    }

    /**
     * @param PanierProduit[] $lignes
     */
    private function resolveCartLineByName(string $requestedName, array $lignes): ?PanierProduit
    {
        $needle = $this->normalizeText($requestedName);
        if ($needle === '') {
            return null;
        }

        foreach ($lignes as $ligne) {
            $nom = $ligne->getProduit()?->getNom();
            if ($nom !== null && $this->normalizeText($nom) === $needle) {
                return $ligne;
            }
        }

        foreach ($lignes as $ligne) {
            $nom = $ligne->getProduit()?->getNom();
            if ($nom === null) {
                continue;
            }

            $normalizedNom = $this->normalizeText($nom);
            if (str_contains($normalizedNom, $needle) || str_contains($needle, $normalizedNom)) {
                return $ligne;
            }
        }

        return null;
    }

    private function normalizeText(string $text): string
    {
        $normalized = trim($text);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = $ascii;
        }

        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9 ]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array{count:int,total:string}
     */
    private function buildCartPayload(?Panier $panier): array
    {
        if (!$panier) {
            return [
                'count' => 0,
                'total' => '0.00',
            ];
        }

        return [
            'count' => $panier->getTotalProduits(),
            'total' => number_format($panier->getTotalMontant(), 2, '.', ' '),
        ];
    }
}

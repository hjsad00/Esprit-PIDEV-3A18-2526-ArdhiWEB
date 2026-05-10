<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Repository\Marketplace\ProduitsRepository;
use App\Service\Marketplace\WishlistNotificationService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\UserAndDiag\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Contrôleur dédié à la gestion des produits de l'utilisateur (Mes Produits).
 */
class ProduitController extends AbstractController
{
    #[Route('/marketplace/mes-produits', name: 'app_marketplace_mes_produits')]
    public function mesProduits(ProduitsRepository $produitsRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $produits = $produitsRepository->findByUser((int) $user->getId());
        $categories = $produitsRepository->findDistinctCategories();

        return $this->render('Marketplace/mes_produits.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'validationErrors' => [],
            'formProduit' => null,
        ]);
    }

    #[Route('/marketplace/mes-produits/save', name: 'app_marketplace_produit_save', methods: ['POST'])]
    public function saveProduit(
        Request $request,
        ProduitsRepository $produitsRepository,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ValidatorInterface $validator,
        WishlistNotificationService $notificationService
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $id = $request->request->get('id');
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if ($id) {
            $produit = $produitsRepository->find($id);
            if (!$produit || !$produit->getUser() || $produit->getUser()->getId() !== $user->getId()) {
                if ($isAjax)
                    return $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);
                $this->addFlash('danger', 'Produit introuvable ou non autorisé.');
                return $this->redirectToRoute('app_marketplace_mes_produits');
            }
            $isNew = false;
            $flashMsg = 'Produit modifié avec succès !';
            $oldPrice = $produit->getPrixFinal(); // Capture du prix avant modif
            $oldStock = $produit->getQuantiteStock(); // Capture du stock avant modif
        } else {
            $produit = new Produits();
            $produit->setUser($user);
            $isNew = true;
            $flashMsg = 'Produit ajouté avec succès !';
            $oldPrice = null;
            $oldStock = 0;
        }

        // Hydratation
        $nom = $request->request->get('nom');
        $produit->setNom(is_scalar($nom) ? (string) $nom : '');

        $description = $request->request->get('description');
        $produit->setDescription(is_scalar($description) ? (string) $description : null);

        $prix = $request->request->get('prix');
        $produit->setPrix(is_numeric($prix) ? (float) $prix : 0.0);

        $quantiteStock = $request->request->get('quantiteStock');
        $produit->setQuantiteStock(is_numeric($quantiteStock) ? (int) $quantiteStock : 0);

        $categorie = $request->request->get('categorie');
        $produit->setCategorie(is_scalar($categorie) ? (string) $categorie : null);

        $uniteMesure = $request->request->get('uniteMesure');
        $produit->setUniteMesure(is_scalar($uniteMesure) ? (string) $uniteMesure : 'Kg');

        // Visibilité
        $visible = $request->request->get('visible') !== null;
        $produit->setVisible($visible);

        $typeRemise = $request->request->get('typeRemise');
        if (is_string($typeRemise) && $typeRemise !== 'AUCUNE') {
            $produit->setTypeRemise($typeRemise);
            $produit->setRemise((float) $request->request->get('remise'));
        } else {
            $produit->setTypeRemise(null);
            $produit->setRemise(0);
        }

        // Validation
        $violations = $validator->validate($produit);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[$v->getPropertyPath()][] = $v->getMessage();
            }

            if ($isAjax) {
                return $this->json(['success' => false, 'errors' => $errors], 400);
            }

            return $this->render('Marketplace/mes_produits.html.twig', [
                'produits' => $produitsRepository->findByUser((int) $user->getId()),
                'categories' => $produitsRepository->findDistinctCategories(),
                'validationErrors' => $errors,
                'formProduit' => $produit,
            ]);
        }

        // Upload image
        $imageFile = $request->files->get('image');
        $newFilename = $produit->getImage() ?? ''; // conserve l'ancienne si pas de nouveau fichier
        if ($imageFile) {
            $safeFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
            try {
                $projectDir = $this->getParameter('kernel.project_dir');
                $projectDir = is_string($projectDir) ? $projectDir : '';
                $imagesDir = $this->getParameter('app.product_images_dir');
                $imagesDir = is_string($imagesDir) ? $imagesDir : '';
                if ($imagesDir === '') {
                    $imagesDir = $projectDir . '/public/uploads/produits';
                }

                if (!is_dir($imagesDir)) {
                    mkdir($imagesDir, 0777, true);
                }

                $imageFile->move($imagesDir, $newFilename);
                $produit->setImage($imagesDir . DIRECTORY_SEPARATOR . $newFilename);
            } catch (FileException $e) {
                if ($isAjax)
                    return $this->json(['success' => false, 'message' => "Erreur upload image."], 500);
                $this->addFlash('warning', "Erreur lors de l'upload de l'image.");
            }
        }

        $em->persist($produit);
        $em->flush();

        // Notification si changement de prix
        if (!$isNew) {
            $notificationService->notifyUpdate($produit, $oldPrice);
        }

        // Notification si stock faible
        if (!$isNew) {
            $notificationService->notifyLowStock($produit, $oldStock);
            $notificationService->notifySellerOutOfStock($produit, $oldStock);
        }

        if ($isAjax) {
            return $this->json([
                'success' => true,
                'message' => $flashMsg,
                'isNew' => $isNew,
                'produit' => [
                    'id' => $produit->getId(),
                    'nom' => $produit->getNom(),
                    'categorie' => $produit->getCategorie() ?? '',
                    'prix' => number_format($produit->getPrix(), 2, '.', ' '),
                    'prixFinal' => number_format($produit->getPrixFinal(), 2, '.', ' '),
                    'stock' => $produit->getQuantiteStock(),
                    'unite' => $produit->getUniteMesure(),
                    'remise' => $produit->getRemise(),
                    'typeRemise' => $produit->getTypeRemise() ?? '',
                    'image' => $produit->getImage() ?? '',
                    'description' => $produit->getDescription() ?? '',
                    'visible' => $produit->isVisible(),
                    'visibleAdmin' => $produit->isVisibleAdmin(),
                ],
            ]);
        }

        $this->addFlash('success', $flashMsg);
        return $this->redirectToRoute('app_marketplace_mes_produits');
    }

    #[Route('/marketplace/mes-produits/delete/{id}', name: 'app_marketplace_produit_delete', methods: ['POST'])]
    public function deleteProduit(
        int $id,
        Request $request,
        ProduitsRepository $produitsRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        $produit = $produitsRepository->find($id);

        if (!$produit || !$produit->getUser() || $produit->getUser()->getId() !== $user->getId()) {
            if ($isAjax)
                return $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);
            $this->addFlash('danger', 'Produit introuvable ou non autorisé.');
            return $this->redirectToRoute('app_marketplace_mes_produits');
        }

        $imageName = $produit->getImage();

        try {
            $em->remove($produit);
            $em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            $message = 'Suppression impossible: ce produit a deja ete achete par des clients, vous ne pouvez pas le supprimer.';

            if ($isAjax) {
                return $this->json(['success' => false, 'message' => $message], 409);
            }

            $this->addFlash('warning', $message);
            return $this->redirectToRoute('app_marketplace_mes_produits');
        } catch (\Throwable $e) {
            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur serveur lors de la suppression du produit.',
                ], 500);
            }

            $this->addFlash('danger', 'Erreur serveur lors de la suppression du produit.');
            return $this->redirectToRoute('app_marketplace_mes_produits');
        }

        if ($imageName) {
            $projectDir = $this->getParameter('kernel.project_dir');
            $projectDir = is_string($projectDir) ? $projectDir : '';
            $path = '';

            if (preg_match('#^https?://#i', $imageName)) {
                $path = '';
            } elseif (preg_match('#^[a-zA-Z]:\\\\#', $imageName) || str_starts_with($imageName, '\\\\')) {
                $path = $imageName;
            } elseif (str_starts_with($imageName, '/')) {
                $path = $projectDir . '/public' . $imageName;
            } else {
                $path = $projectDir . '/public/uploads/produits/' . $imageName;
            }

            if ($path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }

        if ($isAjax)
            return $this->json(['success' => true, 'message' => 'Produit supprimé avec succès !']);

        $this->addFlash('success', 'Produit supprimé avec succès !');
        return $this->redirectToRoute('app_marketplace_mes_produits');
    }

    /**
     * Analyse une image de produit via l'API Groq Vision AI
     * et retourne des recommandations (nom, description, catégorie, unité).
     */
    #[Route('/marketplace/mes-produits/analyze', name: 'app_marketplace_produit_analyze', methods: ['POST'])]
    public function analyzeImage(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $imageFile = $request->files->get('image');
        if (!$imageFile) {
            return $this->json(['success' => false, 'message' => 'Aucune image fournie.'], 400);
        }

        // Validation basique
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($imageFile->getMimeType(), $allowedMimes)) {
            return $this->json(['success' => false, 'message' => 'Format d\'image non supporté. Utilisez JPG, PNG, WebP ou GIF.'], 400);
        }

        if ($imageFile->getSize() > 10 * 1024 * 1024) {
            return $this->json(['success' => false, 'message' => 'L\'image est trop volumineuse (max 10 MB).'], 400);
        }

        // Encoder l'image en base64
        $imageContents = file_get_contents($imageFile->getPathname());
        if ($imageContents === false) {
            return $this->json(['success' => false, 'message' => 'Impossible de lire l\'image.'], 500);
        }
        $imageData = base64_encode($imageContents);
        $mimeType = $imageFile->getMimeType();
        if (!is_string($mimeType)) {
            return $this->json(['success' => false, 'message' => 'Type MIME invalide.'], 400);
        }

        // Clé API et modèle depuis .env
        $apiKeyParam = $this->getParameter('app.marketplace_groq_api_key');
        $apiKey = is_string($apiKeyParam) ? $apiKeyParam : '';
        $modelParam = $this->getParameter('app.marketplace_groq_model');
        $model = is_string($modelParam) ? $modelParam : '';

        if (!$apiKey || $apiKey === 'votre_cle_api_ici') {
            return $this->json(['success' => false, 'message' => 'Clé API Groq non configurée.'], 500);
        }

        // Prompt structuré pour l'IA
        $prompt = <<<PROMPT
Tu es un expert en produits agricoles, alimentaires, et du terroir. Analyse cette image et identifie le produit.
IMPORTANT : L'image doit représenter un produit agricole, végétal, animal, alimentaire, ou un outil agricole (tracteur, engrais...).
Si l'image ne représente PAS un tel produit (ex: une voiture, un ordinateur, une personne de façon isolée non pertinente), tu DOIS mettre "is_agricole" à false et laisser les autres champs vides.

Réponds UNIQUEMENT avec un objet JSON valide (sans markdown, sans ```, juste le JSON brut) avec ces champs :
{
  "is_agricole": true ou false,
  "nom": "Nom du produit (court, en français) si agricole",
  "description": "Description marketplace persuasive et professionnelle : mettez en avant la fraîcheur, l’origine (locale/bio), la qualité premium et les bénéfices pour le client (goût, nutrition, usage culinaire). Ajoutez des éléments rassurants comme la méthode de production, la sélection soignée ou la disponibilité. Rédigez en 2 à 3 phrases claires, engageantes et orientées vente pour donner envie d’acheter immédiatement.",
  "categorie": "Une des catégories: Fruits, Légumes, Céréales, Épices, Produits laitiers, Viandes, Huiles, Semences, Engrais, Outillage, Autre",
  "unite": "Une des unités suivantes: Kg, L, Piece"
}
Si tu ne peux pas identifier le produit avec certitude mais qu'il est agricole, fais ta meilleure estimation.
PROMPT;

        try {
            $response = $httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $prompt,
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => 'data:' . $mimeType . ';base64,' . $imageData,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => 500,
                    'temperature' => 0.3,
                ],
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Nettoyage : extraire le JSON de la réponse
            $content = trim((string) $content);
            // Supprimer les éventuels blocs markdown ```json ... ```
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? '';
            $content = preg_replace('/\s*```$/i', '', $content) ?? '';
            $content = trim($content);

            $result = json_decode($content, true);

            if (!$result) {
                return $this->json([
                    'success' => false,
                    'message' => 'L\'IA n\'a pas pu analyser cette image. Essayez avec une image plus claire.',
                ]);
            }

            // Vérification de la pertinence de l'image
            if (isset($result['is_agricole']) && $result['is_agricole'] === false) {
                return $this->json([
                    'success' => false,
                    'message' => 'Cette image ne semble pas correspondre à un produit agricole ou relatif à Ardhi Marketplace.',
                ]);
            }

            if (!isset($result['nom']) || empty($result['nom'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'L\'IA n\'a pas pu identifier le nom du produit.',
                ]);
            }

            return $this->json([
                'success' => true,
                'nom' => $result['nom'] ?? '',
                'description' => $result['description'] ?? '',
                'categorie' => $result['categorie'] ?? '',
                'unite' => $result['unite'] ?? 'Kg',
            ]);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                    'message' => 'Erreur lors de l\'analyse : ' . $e->getMessage(),
            ], 500);
        }
    }
}

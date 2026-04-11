<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Repository\Marketplace\ProduitsRepository;
use App\Service\Marketplace\WishlistNotificationService;
use Doctrine\ORM\EntityManagerInterface;
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

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $produits = $produitsRepository->findByUser($user->getId());
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

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $id = $request->request->get('id');
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if ($id) {
            $produit = $produitsRepository->find($id);
            if (!$produit || $produit->getUser()->getId() !== $user->getId()) {
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
        $produit->setNom((string) $request->request->get('nom'));
        $produit->setDescription($request->request->get('description'));
        $produit->setPrix((float) $request->request->get('prix'));
        $produit->setQuantiteStock((int) $request->request->get('quantiteStock'));
        $produit->setCategorie($request->request->get('categorie'));
        $produit->setUniteMesure((string) $request->request->get('uniteMesure'));

        // Visibilité
        $visible = $request->request->get('visible') !== null;
        $produit->setVisible($visible);

        $typeRemise = $request->request->get('typeRemise');
        if ($typeRemise && $typeRemise !== 'AUCUNE') {
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
                'produits' => $produitsRepository->findByUser($user->getId()),
                'categories' => $produitsRepository->findDistinctCategories(),
                'validationErrors' => $errors,
                'formProduit' => $produit,
            ]);
        }

        // Upload image
        $imageFile = $request->files->get('image');
        $newFilename = $produit->getImage(); // conserve l'ancienne si pas de nouveau fichier
        if ($imageFile) {
            $safeFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
            try {
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/produits',
                    $newFilename
                );
                $produit->setImage($newFilename);
            } catch (FileException $e) {
                if ($isAjax)
                    return $this->json(['success' => false, 'message' => "Erreur upload image."], 500);
                $this->addFlash('warning', "Erreur lors de l'upload de l'image.");
            }
        }

        $em->persist($produit);
        $em->flush();

        // Notification si changement de prix
        if (!$isNew && $oldPrice !== null) {
            $notificationService->notifyUpdate($produit, $oldPrice);
        }

        // Notification si stock faible
        if (!$isNew) {
            $notificationService->notifyLowStock($produit, $oldStock);
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

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        $produit = $produitsRepository->find($id);

        if (!$produit || $produit->getUser()->getId() !== $user->getId()) {
            if ($isAjax)
                return $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);
            $this->addFlash('danger', 'Produit introuvable ou non autorisé.');
            return $this->redirectToRoute('app_marketplace_mes_produits');
        }

        if ($produit->getImage()) {
            $path = $this->getParameter('kernel.project_dir') . '/public/uploads/produits/' . $produit->getImage();
            if (file_exists($path))
                unlink($path);
        }

        $em->remove($produit);
        $em->flush();

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
        $imageData = base64_encode(file_get_contents($imageFile->getPathname()));
        $mimeType = $imageFile->getMimeType();

        // Clé API et modèle depuis .env
        $apiKey = $this->getParameter('app.marketplace_groq_api_key');
        $model = $this->getParameter('app.marketplace_groq_model');

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
            $content = trim($content);
            // Supprimer les éventuels blocs markdown ```json ... ```
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/i', '', $content);
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

<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MarketplaceController extends AbstractController
{
    #[Route('/marketplace', name: 'app_marketplace')]
    public function index(ProduitsRepository $produitsRepository): Response
    {
        $produits = $produitsRepository->findAll();

        return $this->render('Marketplace/index.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[Route('/marketplace/catalogue', name: 'app_marketplace_catalogue')]
    public function catalogue(ProduitsRepository $produitsRepository): Response
    {
        $user = $this->getUser();
        $produits = $produitsRepository->findAllExceptUser($user ? $user->getId() : null);

        return $this->render('Marketplace/catalogue.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[Route('/marketplace/produit/{id}', name: 'app_marketplace_produit_show')]
    public function show(int $id, ProduitsRepository $produitsRepository): Response
    {
        $produit = $produitsRepository->find($id);

        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('Marketplace/details.html.twig', [
            'produit' => $produit,
        ]);
    }

    // ==================== MES PRODUITS ====================

    #[Route('/marketplace/mes-produits', name: 'app_marketplace_mes_produits')]
    public function mesProduits(ProduitsRepository $produitsRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $produits = $produitsRepository->findByUser($user->getId());
        $categories = $produitsRepository->findDistinctCategories();

        return $this->render('Marketplace/mes_produits.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'validationErrors' => [],      // Pas d'erreurs au chargement initial
            'formProduit' => null,    // Pas de données pré-remplies
        ]);
    }

    #[Route('/marketplace/mes-produits/save', name: 'app_marketplace_produit_save', methods: ['POST'])]
    public function saveProduit(
        Request $request,
        ProduitsRepository $produitsRepository,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ValidatorInterface $validator          // ← Injection du service de validation
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $id = $request->request->get('id');

        if ($id) {
            // Mode édition
            $produit = $produitsRepository->find($id);
            if (!$produit || $produit->getUser() !== $user) {
                $this->addFlash('danger', 'Produit introuvable ou non autorisé.');
                return $this->redirectToRoute('app_marketplace_mes_produits');
            }
            $flashMsg = 'Produit modifié avec succès !';
        } else {
            // Mode création
            $produit = new Produits();
            $produit->setUser($user);
            $flashMsg = 'Produit ajouté avec succès !';
        }

        // ── Hydratation de l'objet depuis le formulaire ──────────────────────
        $produit->setNom((string) $request->request->get('nom'));
        $produit->setDescription($request->request->get('description'));
        $produit->setPrix((float) $request->request->get('prix'));
        $produit->setQuantiteStock((int) $request->request->get('quantiteStock'));
        $produit->setCategorie($request->request->get('categorie'));
        $produit->setUniteMesure((string) $request->request->get('uniteMesure'));

        // Gestion de la remise
        $typeRemise = $request->request->get('typeRemise');
        if ($typeRemise && $typeRemise !== 'AUCUNE') {
            $produit->setTypeRemise($typeRemise);
            $produit->setRemise((float) $request->request->get('remise'));
        } else {
            $produit->setTypeRemise(null);
            $produit->setRemise(0);
        }

        // ── Validation ───────────────────────────────────────────────────────
        $violations = $validator->validate($produit);

        if (count($violations) > 0) {
            // Collecte des messages d'erreur indexés par propriété
            $validationErrors = [];
            foreach ($violations as $violation) {
                $field = $violation->getPropertyPath(); // ex: "nom", "prix", "remise"
                $validationErrors[$field][] = $violation->getMessage();
            }

            // Rechargement de la liste des produits et catégories pour re-rendre la vue complète
            $produits = $produitsRepository->findByUser($user->getId());
            $categories = $produitsRepository->findDistinctCategories();

            // Ré-affichage du formulaire avec les erreurs et les données saisies (pas de redirection)
            return $this->render('Marketplace/mes_produits.html.twig', [
                'produits' => $produits,
                'categories' => $categories,
                'validationErrors' => $validationErrors,
                'formProduit' => $produit,   // Objet hydraté pour pré-remplir les champs
            ]);
        }

        // ── Upload d'image (uniquement si la validation est OK) ───────────────
        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/produits',
                    $newFilename
                );
                $produit->setImage($newFilename);
            } catch (FileException $e) {
                $this->addFlash('warning', "Erreur lors de l'upload de l'image.");
            }
        }

        // ── Persistance ───────────────────────────────────────────────────────
        $em->persist($produit);
        $em->flush();

        $this->addFlash('success', $flashMsg);
        return $this->redirectToRoute('app_marketplace_mes_produits');
    }

    #[Route('/marketplace/mes-produits/delete/{id}', name: 'app_marketplace_produit_delete', methods: ['POST'])]
    public function deleteProduit(
        int $id,
        ProduitsRepository $produitsRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $produit = $produitsRepository->find($id);

        if (!$produit || $produit->getUser() !== $user) {
            $this->addFlash('danger', 'Produit introuvable ou non autorisé.');
            return $this->redirectToRoute('app_marketplace_mes_produits');
        }

        // Supprimer l'image si elle existe
        if ($produit->getImage()) {
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/produits/' . $produit->getImage();
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $em->remove($produit);
        $em->flush();

        $this->addFlash('success', 'Produit supprimé avec succès !');
        return $this->redirectToRoute('app_marketplace_mes_produits');
    }
}
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
    ValidatorInterface $validator
): Response {
    $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

    /** @var \App\Entity\UserAndDiag\User $user */
    $user    = $this->getUser();
    $id      = $request->request->get('id');
    $isAjax  = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

    if ($id) {
        $produit = $produitsRepository->find($id);
        if (!$produit || $produit->getUser()->getId() !== $user->getId()) {
            if ($isAjax) return $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);
            $this->addFlash('danger', 'Produit introuvable ou non autorisé.');
            return $this->redirectToRoute('app_marketplace_mes_produits');
        }
        $isNew    = false;
        $flashMsg = 'Produit modifié avec succès !';
    } else {
        $produit  = new Produits();
        $produit->setUser($user);
        $isNew    = true;
        $flashMsg = 'Produit ajouté avec succès !';
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
            'produits'         => $produitsRepository->findByUser($user->getId()),
            'categories'       => $produitsRepository->findDistinctCategories(),
            'validationErrors' => $errors,
            'formProduit'      => $produit,
        ]);
    }

    // Upload image
    $imageFile = $request->files->get('image');
    $newFilename = $produit->getImage(); // conserve l'ancienne si pas de nouveau fichier
    if ($imageFile) {
        $safeFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
        $newFilename  = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
        try {
            $imageFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/produits',
                $newFilename
            );
            $produit->setImage($newFilename);
        } catch (FileException $e) {
            if ($isAjax) return $this->json(['success' => false, 'message' => "Erreur upload image."], 500);
            $this->addFlash('warning', "Erreur lors de l'upload de l'image.");
        }
    }

    $em->persist($produit);
    $em->flush();

    if ($isAjax) {
        return $this->json([
            'success'   => true,
            'message'   => $flashMsg,
            'isNew'     => $isNew,
            'produit'   => [
                'id'          => $produit->getId(),
                'nom'         => $produit->getNom(),
                'categorie'   => $produit->getCategorie() ?? '',
                'prix'        => number_format($produit->getPrix(), 2, '.', ' '),
                'prixFinal'   => number_format($produit->getPrixFinal(), 2, '.', ' '),
                'stock'       => $produit->getQuantiteStock(),
                'unite'       => $produit->getUniteMesure(),
                'remise'      => $produit->getRemise(),
                'typeRemise'  => $produit->getTypeRemise() ?? '',
                'image'       => $produit->getImage() ?? '',
                'description' => $produit->getDescription() ?? '',
                'visible'     => $produit->isVisible(),
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
    $user    = $this->getUser();
    $isAjax  = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
    $produit = $produitsRepository->find($id);

    if (!$produit || $produit->getUser()->getId() !== $user->getId()) {
        if ($isAjax) return $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        $this->addFlash('danger', 'Produit introuvable ou non autorisé.');
        return $this->redirectToRoute('app_marketplace_mes_produits');
    }

    if ($produit->getImage()) {
        $path = $this->getParameter('kernel.project_dir') . '/public/uploads/produits/' . $produit->getImage();
        if (file_exists($path)) unlink($path);
    }

    $em->remove($produit);
    $em->flush();

    if ($isAjax) return $this->json(['success' => true, 'message' => 'Produit supprimé avec succès !']);

    $this->addFlash('success', 'Produit supprimé avec succès !');
    return $this->redirectToRoute('app_marketplace_mes_produits');
}
}

<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Reclamation;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\Marketplace\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ReclamationController extends AbstractController
{
    #[Route('/marketplace/reclamations', name: 'app_marketplace_reclamations', methods: ['GET'])]
    public function index(ReclamationRepository $reclamationRepo, ProduitsRepository $produitsRepo, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $idProduitFocus = $request->query->get('produit_id');

        // Charger l'historique des réclamations de l'utilisateur
        $reclamations = $reclamationRepo->findBy(['user' => $user], ['dateReclamation' => 'DESC']);
        
        // Calcul des statistiques
        $stats = [
            'total'      => count($reclamations),
            'en_attente' => 0,
            'en_cours'   => 0,
            'resolue'    => 0,
            'rejetee'    => 0,
        ];
        foreach ($reclamations as $rec) {
            $s = strtolower($rec->getStatut());
            if (isset($stats[$s])) {
                $stats[$s]++;
            }
        }
        
        // Charger tous les produits du catalogue sauf ceux de l'utilisateur
        $queryBuilder = $produitsRepo->createQueryBuilder('p')
            ->where('p.user != :user')
            ->setParameter('user', $user)
            ->andWhere('p.visibleAdmin = 1')
            ->orderBy('p.nom', 'ASC');
        $produitsDisponibles = $queryBuilder->getQuery()->getResult();

        return $this->render('Marketplace/reclamations.html.twig', [
            'reclamations' => $reclamations,
            'stats'        => $stats,
            'produits'     => $produitsDisponibles,
            'focus_id'     => $idProduitFocus
        ]);
    }

    #[Route('/marketplace/reclamations/submit', name: 'app_marketplace_reclamations_submit', methods: ['POST'])]
    public function submit(Request $request, ProduitsRepository $produitsRepo, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        
        $idProduit = $data['idProduit'] ?? null;
        $type = $data['type'] ?? '';
        $description = trim($data['description'] ?? '');
        $sujet = trim($data['sujet'] ?? '');
        
        $produit = null;
        if ($idProduit) {
            $produit = $produitsRepo->find($idProduit);
        }
        
        if ($produit && $produit->getUser() && $produit->getUser()->getId() === $user->getId()) {
            return $this->json(['success' => false, 'message' => 'Vous ne pouvez pas signaler votre propre produit.']);
        }
        
        $descriptionFinale = $sujet ? "SUJET : " . mb_strtoupper($sujet) . "\n\n" . $description : $description;

        $reclamation = new Reclamation();
        $reclamation->setUser($user);
        $reclamation->setProduit($produit);
        $reclamation->setNomProduit($produit ? $produit->getNom() : null);
        $reclamation->setType($type);
        $reclamation->setDescription($descriptionFinale);
        $reclamation->setStatut('EN_ATTENTE');
        $reclamation->setDateReclamation(new \DateTime());
        
        // --- NOUVEAU:  Validation pure via l'Entité ---
        $errors = $validator->validate($reclamation);
        if (count($errors) > 0) {
            // Ne retourner que le premier message d'erreur pour rester simple
            $errorMsg = $errors[0]->getMessage();
            return $this->json(['success' => false, 'message' => $errorMsg]);
        }
        
        $em->persist($reclamation);
        $em->flush();
        
        return $this->json([
            'success' => true, 
            'message' => 'Votre réclamation a été envoyée avec succès. Notre équipe l\'examinera dans les plus brefs délais.'
        ]);
    }

    #[Route('/marketplace/reclamations/{id}/details', name: 'app_marketplace_reclamations_details', methods: ['GET'])]
    public function details(int $id, ReclamationRepository $reclamationRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $reclamation = $reclamationRepo->find($id);
        
        if (!$reclamation) {
            return $this->json(['success' => false, 'message' => 'Réclamation introuvable.']);
        }
        
        // Vérifier que l'utilisateur est soit l'auteur, soit admin
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        if ($reclamation->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Accès refusé.']);
        }
        
        return $this->json([
            'success' => true,
            'reclamation' => [
                'id' => $reclamation->getId(),
                'nomProduit' => $reclamation->getNomProduit(),
                'type' => $reclamation->getType(),
                'statut' => $reclamation->getStatut(),
                'date' => $reclamation->getDateReclamation()->format('d/m/Y à H:i'),
                'description' => $reclamation->getDescription()
            ]
        ]);
    }
}

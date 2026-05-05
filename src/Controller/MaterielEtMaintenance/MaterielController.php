<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Entity\MaterielEtMaintenance\Materiel;
use App\Form\MaterielEtMaintenance\MaterielType;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\MaterielEtMaintenance\QrCodeService;

#[Route('/materiel-et-maintenance/materiel', name: 'app_materiel_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MaterielController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, MaterielRepository $repo): Response
    {
        $userId = $this->getUser()->getId();
        $search = $request->query->get('search', '');
        $type   = $request->query->get('type', '');
        $etat   = $request->query->get('etat', '');

        $materiels = $repo->searchByUser($userId, $search ?: null, $type ?: null, $etat ?: null);
        $stats     = $repo->getStatsByUser($userId);

        return $this->render('MaterielEtMaintenance/materiel/index.html.twig', [
            'materiels' => $materiels,
            'stats'     => $stats,
            'search'    => $search,
            'type'      => $type,
            'etat'      => $etat,
            'types'     => ['Tracteur', 'Moissonneuse', 'Semoir', 'Pulvérisateur', 'Charrue', 'Herse', 'Autre'],
            'etats'     => ['Neuf', 'Bon', 'Moyen', 'En panne', 'En maintenance'],
        ]);
    }

    #[Route('/ajouter', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, QrCodeService $qrCodeService): Response
    {
        $materiel = new Materiel();
        $form = $this->createForm(MaterielType::class, $materiel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/materiel',
                        $newFilename
                    );
                    $materiel->setImage($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur si nécessaire
                }
            }

            $materiel->setUser($this->getUser());
            
            // Initialisation du seuil par défaut si non précisé
            if (!$materiel->getSeuilMaintenanceHeures()) {
                $materiel->initialiserSeuilParDefaut();
            }
            
            $materiel->calculerProchaineMaintenance();

            $em->persist($materiel);
            $em->flush(); // Nécessaire pour avoir le token généré par PrePersist

            // Génération du QR Code
            $qrPath = $qrCodeService->generateForMateriel($materiel);
            $materiel->setQrCodePath($qrPath);
            $em->flush();

            $this->addFlash('success', 'Matériel "' . $materiel->getNom() . '" ajouté avec succès ! QR Code généré.');
            return $this->redirectToRoute('app_materiel_index');
        }

        return $this->render('MaterielEtMaintenance/materiel/new.html.twig', [
            'form' => $form->createView(),
            'materiel' => $materiel,
        ]);
    }

    #[Route('/ia-dashboard', name: 'ia_dashboard', methods: ['GET'])]
    public function iaDashboard(MaterielRepository $repo, \App\Repository\MaterielEtMaintenance\AlerteTechnicienRepository $alerteRepo): Response
    {
        $user = $this->getUser();
        $userId = $user->getId();
        $materiels = $repo->findBy(['user' => $user]);
        
        // Compter les alertes non lues pour l'agriculteur
        $countUnread = $alerteRepo->countUnreadForAgriculteur($userId);

        return $this->render('MaterielEtMaintenance/materiel/ia_dashboard.html.twig', [
            'materiels' => $materiels,
            'countUnread' => $countUnread
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, MaterielRepository $repo, QrCodeService $qrCodeService, EntityManagerInterface $em, \App\Repository\MaterielEtMaintenance\AlerteTechnicienRepository $alerteRepo): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);

        // Si le QR code n'existe pas encore ou si le fichier physique a été supprimé, on le génère à la volée
        $projectDir = $this->getParameter('kernel.project_dir');
        $fullPath = $materiel->getQrCodePath() ? $projectDir . '/public/' . $materiel->getQrCodePath() : null;

        if (!$materiel->getQrCodePath() || ($fullPath && !file_exists($fullPath))) {
            // S'assurer qu'un token existe déjà (pour les anciens matériels créés avant la mise à jour)
            if (!$materiel->getQrCodeToken()) {
                $materiel->setQrCodeToken(bin2hex(random_bytes(16)));
            }
            $qrPath = $qrCodeService->generateForMateriel($materiel);
            $materiel->setQrCodePath($qrPath);
            $em->flush();
        }

        $countUnread = $alerteRepo->count(['materiel' => $materiel, 'statut' => 'non_lu']);

        return $this->render('MaterielEtMaintenance/materiel/show.html.twig', [
            'materiel' => $materiel,
            'countUnread' => $countUnread,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, MaterielRepository $repo, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);
        $form = $this->createForm(MaterielType::class, $materiel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/materiel',
                        $newFilename
                    );
                    
                    // Optionnel : supprimer l'ancienne image si elle existe
                    if ($materiel->getImage()) {
                        $oldImagePath = $this->getParameter('kernel.project_dir').'/public/uploads/materiel/'.$materiel->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $materiel->setImage($newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur si nécessaire
                }
            }

            $materiel->calculerProchaineMaintenance();

            $em->flush();
            $this->addFlash('success', 'Matériel modifié avec succès !');
            return $this->redirectToRoute('app_materiel_index');
        }

        return $this->render('MaterielEtMaintenance/materiel/edit.html.twig', [
            'form' => $form->createView(),
            'materiel' => $materiel,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, MaterielRepository $repo, ProduitsRepository $produitsRepository, EntityManagerInterface $em): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);

        if ($this->isCsrfTokenValid('delete_materiel_' . $id, $request->request->get('_token'))) {
            // Supprimer d'abord les produits liés à ce matériel pour éviter la violation FK.
            $produitsAssocies = $produitsRepository->findBy(['materiel' => $materiel]);
            $nombreProduitsSupprimes = count($produitsAssocies);

            foreach ($produitsAssocies as $produit) {
                if ($produit->getImage()) {
                    $imageProduitPath = $this->getParameter('kernel.project_dir') . '/public/uploads/produits/' . $produit->getImage();
                    if (is_file($imageProduitPath)) {
                        unlink($imageProduitPath);
                    }
                }

                $em->remove($produit);
            }

            $em->remove($materiel);
            $em->flush();

            if ($nombreProduitsSupprimes > 0) {
                $this->addFlash('success', sprintf('Matériel supprimé avec succès. %d produit(s) lié(s) ont aussi été supprimé(s).', $nombreProduitsSupprimes));
            } else {
                $this->addFlash('success', 'Matériel supprimé avec succès.');
            }
        } else {
            $this->addFlash('danger', 'Action non autorisée.');
        }

        return $this->redirectToRoute('app_materiel_index');
    }

    #[Route('/{id}/transition/{transition}', name: 'transition', methods: ['POST'])]
    public function applyTransition(
        int $id, 
        string $transition, 
        Request $request, 
        MaterielRepository $repo, 
        WorkflowInterface $materielLifecycleStateMachine, 
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);

        if (!$this->isCsrfTokenValid('workflow_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
        }

        // --- Validation PHP Spécifique pour l'Urgence ---
        if ($transition === 'mettre_en_maintenance') {
            $description = trim($request->request->get('description', ''));
            $errors = $validator->validate($description, [
                new NotBlank(['message' => 'La description doit être remplie pour signaler une urgence.']),
                new Length([
                    'min' => 10,
                    'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.'
                ])
            ]);

            if (count($errors) > 0) {
                $this->addFlash('danger', $errors[0]->getMessage());
                return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
            }
        }

        if ($materielLifecycleStateMachine->can($materiel, $transition)) {
            $materielLifecycleStateMachine->apply($materiel, $transition);
            
            if ($transition === 'mettre_en_maintenance') {
                $materiel->setEtat('En panne');
                $maintenance = new Maintenance();
                $maintenance->setMateriel($materiel);
                $maintenance->setStatutMaintenance('en_attente');
                $maintenance->setTypeMaintenance('urgente');
                $maintenance->setDateMaintenance(new \DateTime());
                $maintenance->setDescription(trim((string) $request->request->get('description')));
                
                $em->persist($maintenance);
            }

            $em->flush();
            if ($transition === 'mettre_en_maintenance') {
                $this->addFlash('success', 'Demande urgente envoyée avec succès. L\'administrateur a été notifié.');
            } else {
                $this->addFlash('success', 'Action effectuée avec succès.');
            }
        } else {
            $this->addFlash('danger', 'Impossible d\'appliquer cette transition.');
        }

        return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
    }

    #[Route('/{id}/mettre-en-vente', name: 'mettre_en_vente', methods: ['POST'])]
    public function mettreEnVente(
        int $id,
        Request $request,
        MaterielRepository $repo,
        WorkflowInterface $materielLifecycleStateMachine,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);

        if (!$this->isCsrfTokenValid('vente_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
        }

        if (!$materielLifecycleStateMachine->can($materiel, 'mettre_en_vente')) {
            $this->addFlash('danger', 'Ce matériel ne peut pas être mis en vente dans son état actuel.');
            return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
        }

        $description = trim($request->request->get('description', ''));
        $prix = (float) $request->request->get('prix', 0);

        if (empty($description)) {
            $this->addFlash('danger', 'La description est obligatoire pour mettre en vente.');
            return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
        }

        if ($prix <= 0) {
            $this->addFlash('danger', 'Le prix doit être supérieur à 0.');
            return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
        }

        $categorieMap = [
            'Tracteur' => 'Outillage', 'Moissonneuse' => 'Outillage',
            'Semoir' => 'Outillage', 'Pulvérisateur' => 'Outillage',
            'Charrue' => 'Outillage', 'Herse' => 'Outillage', 'Autre' => 'Autre',
        ];

        $produit = new \App\Entity\Marketplace\Produits();
        $produit->setNom($materiel->getNom());
        $produit->setDescription($description);
        $produit->setPrix($prix);
        $produit->setQuantiteStock(1);
        $produit->setCategorie($categorieMap[$materiel->getType()] ?? 'Outillage');
        $produit->setUniteMesure('Piece');
        $produit->setRemise(0);
        $produit->setTypeRemise(null);
        $produit->setVisible(true);
        $produit->setVisibleAdmin(true);
        $produit->setUser($this->getUser());
        $produit->setMateriel($materiel);

        if ($materiel->getImage()) {
            $srcPath = $this->getParameter('kernel.project_dir') . '/public/uploads/materiel/' . $materiel->getImage();
            if (file_exists($srcPath)) {
                $ext = pathinfo($materiel->getImage(), PATHINFO_EXTENSION);
                $newFilename = 'materiel-' . $materiel->getId() . '-' . uniqid() . '.' . $ext;
                $destPath = $this->getParameter('kernel.project_dir') . '/public/uploads/produits/' . $newFilename;
                copy($srcPath, $destPath);
                $produit->setImage($newFilename);
            }
        }

        $materielLifecycleStateMachine->apply($materiel, 'mettre_en_vente');

        $em->persist($produit);
        $em->flush();

        $this->addFlash('success', '🎉 Votre matériel "' . $materiel->getNom() . '" est maintenant en vente sur le Marketplace !');
        return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
    }

    #[Route('/{id}/heures', name: 'update_hours', methods: ['POST'])]

    public function updateHours(int $id, Request $request, MaterielRepository $repo, EntityManagerInterface $em): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);

        // Sécurité : Ne pas autoriser la mise à jour si en vente, vendu ou réformé
        if (in_array($materiel->getStatut(), ['en_vente', 'vendu', 'reforme'])) {
            $this->addFlash('danger', 'Le compteur d\'heures est gelé pour ce matériel.');
            return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
        }

        $nouvellesHeures = (int) $request->request->get('heures');


        if ($nouvellesHeures < $materiel->getHeuresUtilisation()) {
            $this->addFlash('danger', 'Le nouveau compteur ne peut pas être inférieur à l\'ancien.');
            return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
        }

        $materiel->setHeuresUtilisation($nouvellesHeures);
        $em->flush();

        $this->addFlash('success', 'Compteur d\'heures mis à jour.');
        
        // Note: La commande de vérification sera lancée via CRON, mais on pourrait aussi vérifier ici
        // si un seuil est atteint pour prévenir l'utilisateur immédiatement.

        return $this->redirectToRoute('app_materiel_show', ['id' => $id]);
    }

    #[Route('/{id}/ia-analyse', name: 'ia_analyse', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function iaAnalyse(int $id, MaterielRepository $repo, \App\Service\MaterielEtMaintenance\GroqPredictionService $groqService): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);
        $prediction = $groqService->generatePrediction($materiel);

        return new \Symfony\Component\HttpFoundation\JsonResponse($prediction);
    }



    private function getMaterielOwnedByUser(int $id, MaterielRepository $repo): Materiel
    {
        $materiel = $repo->find($id);
        if (!$materiel || $materiel->getUser()?->getId() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Matériel introuvable.');
        }
        return $materiel;
    }
}

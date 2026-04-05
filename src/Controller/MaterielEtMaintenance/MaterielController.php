<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Form\MaterielEtMaintenance\MaterielType;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

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
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
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

            $materiel->setUserId($this->getUser()->getId());

            // Calcul date prochaine maintenance si date achat + fréquence fournis
            if ($materiel->getDateAchat() && $materiel->getFrequenceMaintenanceMois()) {
                $prochaine = clone $materiel->getDateAchat();
                $prochaine->modify('+' . $materiel->getFrequenceMaintenanceMois() . ' months');
                $materiel->setDateProchaineMaintenance($prochaine);
            }

            $em->persist($materiel);
            $em->flush();

            $this->addFlash('success', 'Matériel "' . $materiel->getNom() . '" ajouté avec succès !');
            return $this->redirectToRoute('app_materiel_index');
        }

        return $this->render('MaterielEtMaintenance/materiel/new.html.twig', [
            'form' => $form->createView(),
            'materiel' => $materiel,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, MaterielRepository $repo): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);

        return $this->render('MaterielEtMaintenance/materiel/show.html.twig', [
            'materiel' => $materiel,
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

            // Recalcul date prochaine maintenance
            if ($materiel->getDateAchat() && $materiel->getFrequenceMaintenanceMois()) {
                $base = $materiel->getDerniereMaintenance() ?? $materiel->getDateAchat();
                $prochaine = clone $base;
                $prochaine->modify('+' . $materiel->getFrequenceMaintenanceMois() . ' months');
                $materiel->setDateProchaineMaintenance($prochaine);
            }

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
    public function delete(int $id, Request $request, MaterielRepository $repo, EntityManagerInterface $em): Response
    {
        $materiel = $this->getMaterielOwnedByUser($id, $repo);

        if ($this->isCsrfTokenValid('delete_materiel_' . $id, $request->request->get('_token'))) {
            $em->remove($materiel);
            $em->flush();
            $this->addFlash('success', 'Matériel supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Action non autorisée.');
        }

        return $this->redirectToRoute('app_materiel_index');
    }

    private function getMaterielOwnedByUser(int $id, MaterielRepository $repo): Materiel
    {
        $materiel = $repo->find($id);
        if (!$materiel || $materiel->getUserId() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Matériel introuvable.');
        }
        return $materiel;
    }
}

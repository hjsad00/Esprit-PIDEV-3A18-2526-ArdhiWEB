<?php

namespace App\Controller\Admin;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Form\MaterielEtMaintenance\AdminMaterielType;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/materiel', name: 'admin_materiel_')]
#[IsGranted('ROLE_ADMIN')]
class AdminMaterielController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, MaterielRepository $repo, UserRepository $userRepo): Response
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $etat = $request->query->get('etat', '');

        $materielsList = $repo->findAllForAdmin($search ?: null, $type ?: null, $etat ?: null);
        $now = new \DateTime('today');

        $materiels = [];
        foreach ($materielsList as $materiel) {
            $daysLeft = null;
            if ($materiel->getDateProchaineMaintenance()) {
                $targetDate = \DateTime::createFromInterface($materiel->getDateProchaineMaintenance());
                $targetDate->setTime(0, 0, 0);
                $diff = $now->diff($targetDate);
                $daysLeft = (int)$diff->format('%r%a');
            }
            
            $materiels[] = [
                'obj' => $materiel,
                'daysLeft' => $daysLeft
            ];
        }
        
        $users = $userRepo->findAll();
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user->getId()] = $user;
        }

        return $this->render('admin/materiel/index.html.twig', [
            'materiels' => $materiels,
            'userMap' => $userMap,
            'search' => $search,
            'type' => $type,
            'etat' => $etat,
            'types' => ['Tracteur', 'Moissonneuse', 'Semoir', 'Pulvérisateur', 'Charrue', 'Herse', 'Autre'],
            'etats' => ['Neuf', 'Bon', 'Moyen', 'En panne', 'En maintenance'],
            'now' => $now,
        ]);
    }

    #[Route('/ajouter', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $materiel = new Materiel();
        $form = $this->createForm(AdminMaterielType::class, $materiel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'utilisateur sélectionné
            $user = $form->get('userEntity')->getData();
            if ($user) {
                $materiel->setUserId($user->getId());
            }

            // Gestion de l'image
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
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            $materiel->setFrequenceMaintenanceMois(6);
            $baseDate = $materiel->getDateAchat() ? clone $materiel->getDateAchat() : new \DateTime();
            $baseDate->modify('+6 months');
            $materiel->setDateProchaineMaintenance($baseDate);

            $em->persist($materiel);
            $em->flush();

            $this->addFlash('success', 'Matériel ajouté avec succès pour ' . $user->getPrenom() . ' ' . $user->getNom());
            return $this->redirectToRoute('admin_materiel_index');
        }

        return $this->render('admin/materiel/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, MaterielRepository $repo, UserRepository $userRepo, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $materiel = $repo->find($id);
        if (!$materiel) {
            throw $this->createNotFoundException('Matériel introuvable.');
        }

        $form = $this->createForm(AdminMaterielType::class, $materiel);
        
        // Pré-remplir l'utilisateur dans le champ mapped=>false
        $currentUser = $userRepo->find($materiel->getUserId());
        if ($currentUser) {
            $form->get('userEntity')->setData($currentUser);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->get('userEntity')->getData();
            if ($user) {
                $materiel->setUserId($user->getId());
            }

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
                    if ($materiel->getImage()) {
                        $oldPath = $this->getParameter('kernel.project_dir').'/public/uploads/materiel/'.$materiel->getImage();
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    $materiel->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur image.');
                }
            }

            $em->flush();
            $this->addFlash('success', 'Matériel mis à jour.');
            return $this->redirectToRoute('admin_materiel_index');
        }

        return $this->render('admin/materiel/edit.html.twig', [
            'form' => $form->createView(),
            'materiel' => $materiel,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, MaterielRepository $repo, EntityManagerInterface $em): Response
    {
        $materiel = $repo->find($id);
        if ($materiel && $this->isCsrfTokenValid('delete_materiel_'.$id, $request->request->get('_token'))) {
            $em->remove($materiel);
            $em->flush();
            $this->addFlash('success', 'Matériel supprimé.');
        }
        return $this->redirectToRoute('admin_materiel_index');
    }
}

<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Employe;
use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employes')]
class EmployeController extends AbstractController
{
    // ── Liste ─────────────────────────────────────────────────────────────

    #[Route('', name: 'employe_index')]
    public function index(EmployeRepository $repo, Request $request): Response
    {
        $search = $request->query->get('search', '');

        // Multi-tenant : on utilise l'id du user connecté comme id_agriculteur
        $idAgriculteur = $this->getUser()?->getId();

        $employes = $search
            ? $repo->search($search, $idAgriculteur)
            : $repo->findByAgriculteur($idAgriculteur);

        return $this->render('EmployeTache/employe/index.html.twig', [
            'employes'      => $employes,
            'search'        => $search,
            'total'         => count($employes),
            'total_actifs'  => count(array_filter($employes, fn($e) => $e->isActif())),
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────

    #[Route('/new', name: 'employe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('employe_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token invalide.');
                return $this->redirectToRoute('employe_new');
            }

            $employe = new Employe();
            $this->handleForm($request, $employe);
            $employe->setIdAgriculteur($this->getUser()?->getId());

            $em->persist($employe);
            $em->flush();

            // Générer le QR code unique après flush (ID disponible)
            $employe->setQrCodeUnique($employe->genererQrCodeUnique());

            // Gérer l'upload de photo
            $photoFile = $request->files->get('photo');
            if ($photoFile) {
                $photoPath = $this->uploadPhoto($photoFile, $employe->getId());
                if ($photoPath) $employe->setPhotoPath($photoPath);
            }

            $em->flush();

            $this->addFlash('success', 'Employé ' . $employe->getNomComplet() . ' créé avec succès.');
            return $this->redirectToRoute('employe_index');
        }

        return $this->render('EmployeTache/employe/form.html.twig', [
            'page_title'    => 'Ajouter un Employé',
            'employe'       => null,
            'csrf_token_id' => 'employe_form',
            'cancel_route'  => 'employe_index',
        ]);
    }

    // ── Modification ──────────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'employe_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);
        if (!$employe) throw $this->createNotFoundException('Employé introuvable.');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('employe_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token invalide.');
                return $this->redirectToRoute('employe_edit', ['id' => $id]);
            }

            $this->handleForm($request, $employe);

            // Gérer l'upload de photo
            $photoFile = $request->files->get('photo');
            if ($photoFile) {
                $photoPath = $this->uploadPhoto($photoFile, $employe->getId());
                if ($photoPath) $employe->setPhotoPath($photoPath);
            }

            $em->flush();

            $this->addFlash('success', 'Employé ' . $employe->getNomComplet() . ' modifié avec succès.');
            return $this->redirectToRoute('employe_index');
        }

        return $this->render('EmployeTache/employe/form.html.twig', [
            'page_title'    => 'Modifier — ' . $employe->getNomComplet(),
            'employe'       => $employe,
            'csrf_token_id' => 'employe_form',
            'cancel_route'  => 'employe_index',
        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'employe_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);

        if ($employe && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $nom = $employe->getNomComplet();
            $em->remove($employe);
            $em->flush();
            $this->addFlash('success', 'Employé ' . $nom . ' supprimé.');
        }

        return $this->redirectToRoute('employe_index');
    }

    // ── Détail ────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'employe_show', methods: ['GET'])]
    public function show(int $id, EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);
        if (!$employe) throw $this->createNotFoundException('Employé introuvable.');

        return $this->render('EmployeTache/employe/show.html.twig', [
            'employe' => $employe,
        ]);
    }

    // ── Activer / Désactiver ──────────────────────────────────────────────

    #[Route('/{id}/toggle', name: 'employe_toggle', methods: ['POST'])]
    public function toggle(int $id, EntityManagerInterface $em, EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);
        if ($employe) {
            $employe->setActif(!$employe->isActif());
            $em->flush();
            $statut = $employe->isActif() ? 'activé' : 'désactivé';
            $this->addFlash('success', $employe->getNomComplet() . ' ' . $statut . '.');
        }
        return $this->redirectToRoute('employe_index');
    }

    // ── Utilitaires privés ────────────────────────────────────────────────

    private function handleForm(Request $r, Employe $employe): void
    {
        $employe->setNom(trim($r->request->get('nom', '')));
        $employe->setPrenom(trim($r->request->get('prenom', '')));
        $employe->setEmail(trim($r->request->get('email', '')));
        $employe->setPoste($r->request->get('poste') ?: null);
        $employe->setTelephone($r->request->get('telephone') ?: null);
        $employe->setActif($r->request->get('actif') === '1');
    }

    private function uploadPhoto(\Symfony\Component\HttpFoundation\File\UploadedFile $file, int $idEmploye): ?string
    {
        try {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/employes/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $filename = 'EMP_' . $idEmploye . '_' . time() . '.' . $file->guessExtension();
            $file->move($uploadDir, $filename);
            return '/uploads/employes/' . $filename;
        } catch (\Exception $e) {
            return null;
        }
    }
}
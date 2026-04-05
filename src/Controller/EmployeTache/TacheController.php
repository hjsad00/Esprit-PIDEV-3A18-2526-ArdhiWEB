<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Tache;
use App\Entity\EmployeTache\Employe;
use App\Repository\EmployeTache\TacheRepository;
use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/taches')]
#[IsGranted('ROLE_AGRICULTEUR')]
class TacheController extends AbstractController
{
    // Critères de tri autorisés — identiques aux CRITERES_TRI desktop
    private const TRIS_VALIDES = ['id','titre','statut','priorite','dateDebut','dateFin','categorie'];

    // ── Liste + Filtres + Tri ─────────────────────────────────────────

    #[Route('', name: 'tache_index')]
    public function index(TacheRepository $repo, EmployeRepository $empRepo, Request $request): Response
    {
        $idAgriculteur = $this->getUser()->getId();

        // Paramètres GET — persistés dans l'URL
        $search    = $request->query->get('search', '');
        $statut    = $request->query->get('statut', 'Tous');
        $priorite  = $request->query->get('priorite', 'Toutes');
        $categorie = $request->query->get('categorie', 'Toutes');
        $tri       = $request->query->get('tri', 'dateDebut');
        $direction = $request->query->get('direction', 'asc');

        // Sécurisation
        if (!in_array($tri, self::TRIS_VALIDES, true)) $tri = 'dateDebut';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $taches = $repo->findFiltreeTrie($idAgriculteur, $search, $statut, $priorite, $categorie, $tri, $direction);
        $kpis   = $repo->countByStatut($idAgriculteur);

        // Employés actifs pour l'affichage du nom dans la table
        $employes = $empRepo->findActifsByAgriculteur($idAgriculteur);
        $mapEmployes = [];
        foreach ($employes as $emp) {
            $mapEmployes[$emp->getId()] = $emp->getNomComplet();
        }

        return $this->render('EmployeTache/tache/index.html.twig', [
            'taches'      => $taches,
            'kpis'        => $kpis,
            'employes'    => $employes,
            'mapEmployes' => $mapEmployes,
            'search'      => $search,
            'statut'      => $statut,
            'priorite'    => $priorite,
            'categorie'   => $categorie,
            'tri'         => $tri,
            'direction'   => $direction,
            // Listes pour les filtres
            'statuts'     => ['Tous', ...Tache::STATUTS],
            'priorites'   => ['Toutes', 'Basse', 'Moyenne', 'Haute', 'Critique'],
            'categories'  => ['Toutes', ...Tache::CATEGORIES],
        ]);
    }

    // ── Création ─────────────────────────────────────────────────────

    #[Route('/new', name: 'tache_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em,
                        ValidatorInterface $validator, EmployeRepository $empRepo): Response
    {
        $errors    = [];
        $old       = [];
        $employes  = $empRepo->findActifsByAgriculteur($this->getUser()->getId());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tache_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('tache_new');
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator);

            if (empty($errors)) {
                $tache = new Tache();
                $this->hydraterTache($tache, $data);
                $tache->setIdAgriculteur($this->getUser()->getId());

                $em->persist($tache);
                $em->flush();

                $this->addFlash('success', '✅ Tâche "' . $tache->getTitre() . '" créée avec succès.');
                return $this->redirectToRoute('tache_index');
            }
        }

        return $this->render('EmployeTache/tache/form.html.twig', [
            'page_title'    => 'Ajouter une Tâche',
            'tache'         => null,
            'employes'      => $employes,
            'errors'        => $errors,
            'old'           => $old,
            'csrf_token_id' => 'tache_form',
        ]);
    }

    // ── Modification ──────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'tache_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em,
                         ValidatorInterface $validator, TacheRepository $repo,
                         EmployeRepository $empRepo): Response
    {
        $tache = $repo->find($id);
        if (!$tache || $tache->getIdAgriculteur() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Tâche introuvable.');
        }

        $errors   = [];
        $old      = [];
        $employes = $empRepo->findActifsByAgriculteur($this->getUser()->getId());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tache_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('tache_edit', ['id' => $id]);
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator);

            if (empty($errors)) {
                $this->hydraterTache($tache, $data);
                $em->flush();

                $this->addFlash('success', '✅ Tâche "' . $tache->getTitre() . '" modifiée.');
                return $this->redirectToRoute('tache_index');
            }
        }

        return $this->render('EmployeTache/tache/form.html.twig', [
            'page_title'    => 'Modifier — ' . $tache->getTitre(),
            'tache'         => $tache,
            'employes'      => $employes,
            'errors'        => $errors,
            'old'           => $old,
            'csrf_token_id' => 'tache_form',
        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'tache_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em,
                           TacheRepository $repo): Response
    {
        $tache = $repo->find($id);
        if ($tache && $tache->getIdAgriculteur() === $this->getUser()->getId()
            && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $titre = $tache->getTitre();
            $em->remove($tache);
            $em->flush();
            $this->addFlash('success', '🗑️ Tâche "' . $titre . '" supprimée.');
        }
        return $this->redirectToRoute('tache_index');
    }

    // ── Changement de statut rapide ───────────────────────────────────

    #[Route('/{id}/statut/{statut}', name: 'tache_statut', methods: ['POST'])]
    public function changerStatut(int $id, string $statut, EntityManagerInterface $em,
                                   TacheRepository $repo): Response
    {
        $tache = $repo->find($id);
        if ($tache && $tache->getIdAgriculteur() === $this->getUser()->getId()
            && in_array($statut, Tache::STATUTS, true)) {
            $tache->setStatut($statut);
            $em->flush();
            $this->addFlash('success', '"' . $tache->getTitre() . '" → ' . $statut);
        }
        return $this->redirectToRoute('tache_index');
    }

    // ══════════════════════════════════════════════════════════════════
    // VALIDATION SERVEUR — identique au desktop
    // ══════════════════════════════════════════════════════════════════

    private function validerDonnees(array $data, ValidatorInterface $validator): array
    {
        $errors = [];

        // Titre
        $v = $validator->validate($data['titre'], [
            new Assert\NotBlank(message: 'Le titre est obligatoire.'),
            new Assert\Length(max: 200, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'),
        ]);
        if (count($v)) $errors['titre'] = $v[0]->getMessage();

        // Description (obligatoire comme dans le desktop)
        $v = $validator->validate($data['description'], [
            new Assert\NotBlank(message: 'La description est obligatoire.'),
        ]);
        if (count($v)) $errors['description'] = $v[0]->getMessage();

        // Statut
        $v = $validator->validate($data['statut'], [
            new Assert\NotBlank(message: 'Le statut est obligatoire.'),
            new Assert\Choice(choices: Tache::STATUTS, message: 'Statut invalide.'),
        ]);
        if (count($v)) $errors['statut'] = $v[0]->getMessage();

        // Priorité
        $v = $validator->validate($data['priorite'], [
            new Assert\NotBlank(message: 'La priorité est obligatoire.'),
            new Assert\Choice(choices: [1, 2, 3, 4], message: 'Priorité invalide.'),
        ]);
        if (count($v)) $errors['priorite'] = $v[0]->getMessage();

        // Date début
        if ($data['dateDebut'] === null) {
            $errors['dateDebut'] = 'La date de début est obligatoire.';
        }

        // Date fin ≥ date début (identique à la validation desktop)
        if ($data['dateDebut'] !== null && $data['dateFin'] !== null) {
            if ($data['dateFin'] < $data['dateDebut']) {
                $errors['dateFin'] = '⚠ Date fin < date début.';
            }
        }

        // Employé obligatoire (identique au desktop)
        if ($data['idEmploye'] === null) {
            $errors['idEmploye'] = 'L\'employé est obligatoire.';
        }

        return $errors;
    }

    private function extractFormData(Request $r): array
    {
        $dateDebut = null;
        $dateFin   = null;

        if ($r->request->get('dateDebut')) {
            try { $dateDebut = new \DateTime($r->request->get('dateDebut')); } catch (\Exception) {}
        }
        if ($r->request->get('dateFin')) {
            try { $dateFin = new \DateTime($r->request->get('dateFin')); } catch (\Exception) {}
        }

        return [
            'titre'       => trim($r->request->get('titre', '')),
            'description' => trim($r->request->get('description', '')),
            'statut'      => $r->request->get('statut', Tache::STATUT_EN_ATTENTE),
            'priorite'    => $r->request->get('priorite') ? (int)$r->request->get('priorite') : null,
            'categorie'   => $r->request->get('categorie') ?: 'Plantation',
            'dateDebut'   => $dateDebut,
            'dateFin'     => $dateFin,
            'idEmploye'   => $r->request->get('idEmploye') ? (int)$r->request->get('idEmploye') : null,
        ];
    }

    private function hydraterTache(Tache $tache, array $data): void
    {
        $tache->setTitre($data['titre']);
        $tache->setDescription($data['description'] ?: null);
        $tache->setStatut($data['statut']);
        $tache->setPriorite($data['priorite']);
        $tache->setCategorie($data['categorie']);
        $tache->setDateDebut($data['dateDebut']);
        $tache->setDateFin($data['dateFin']);
        $tache->setIdEmploye($data['idEmploye']);
    }
}
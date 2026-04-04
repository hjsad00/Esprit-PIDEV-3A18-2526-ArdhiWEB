<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Employe;
use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/employes')]
#[IsGranted('ROLE_AGRICULTEUR')]
class EmployeController extends AbstractController
{
    // Critères de tri autorisés — identiques au desktop (CRITERES_TRI)
    private const TRIS_VALIDES = ['id', 'nom', 'prenom', 'email', 'poste', 'actif', 'telephone'];

    // ── Liste + Recherche + Tri ───────────────────────────────────────────

    #[Route('', name: 'employe_index')]
    public function index(EmployeRepository $repo, Request $request): Response
    {
        $idAgriculteur = $this->getUser()->getId();

        // Paramètres GET (persistés dans l'URL pour garder le tri après navigation)
        $search    = $request->query->get('search', '');
        $tri       = $request->query->get('tri', 'nom');
        $direction = $request->query->get('direction', 'asc');

        // Sécurisation des paramètres
        if (!in_array($tri, self::TRIS_VALIDES, true)) {
            $tri = 'nom';
        }
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        // Une seule requête fait tout : recherche + tri
        $employes = $repo->findByAgriculteurTrie($idAgriculteur, $tri, $direction, $search);

        return $this->render('EmployeTache/employe/index.html.twig', [
            'employes'     => $employes,
            'search'       => $search,
            'tri'          => $tri,
            'direction'    => $direction,
            'total'        => count($employes),
            'total_actifs' => count(array_filter($employes, fn($e) => $e->isActif())),
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────────

    #[Route('/new', name: 'employe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em,
                        ValidatorInterface $validator, EmployeRepository $repo): Response
    {
        $errors = [];
        $old    = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('employe_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('employe_new');
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator, $repo);
            $errors = array_merge($errors, $this->validerPhoto($request->files->get('photo')));

            if (empty($errors)) {
                $employe = new Employe();
                $this->hydraterEmploye($employe, $data);
                $employe->setIdAgriculteur($this->getUser()->getId());

                $em->persist($employe);
                $em->flush();

                $employe->setQrCodeUnique($employe->genererQrCodeUnique());

                $photoFile = $request->files->get('photo');
                if ($photoFile) {
                    $path = $this->uploadPhoto($photoFile, $employe->getId());
                    if ($path) $employe->setPhotoPath($path);
                }
                $em->flush();

                $this->addFlash('success', '✅ Employé ' . $employe->getNomComplet() . ' créé avec succès.');
                return $this->redirectToRoute('employe_index');
            }
        }

        return $this->render('EmployeTache/employe/form.html.twig', [
            'page_title'    => 'Ajouter un Employé',
            'employe'       => null,
            'errors'        => $errors,
            'old'           => $old,
            'csrf_token_id' => 'employe_form',
        ]);
    }

    // ── Modification ──────────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'employe_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em,
                         ValidatorInterface $validator, EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        $errors = [];
        $old    = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('employe_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('employe_edit', ['id' => $id]);
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator, $repo, $id);
            $errors = array_merge($errors, $this->validerPhoto($request->files->get('photo')));

            if (empty($errors)) {
                $this->hydraterEmploye($employe, $data);

                $photoFile = $request->files->get('photo');
                if ($photoFile) {
                    $path = $this->uploadPhoto($photoFile, $employe->getId());
                    if ($path) $employe->setPhotoPath($path);
                }
                $em->flush();

                $this->addFlash('success', '✅ Employé ' . $employe->getNomComplet() . ' modifié.');
                return $this->redirectToRoute('employe_index');
            }
        }

        return $this->render('EmployeTache/employe/form.html.twig', [
            'page_title'    => 'Modifier — ' . $employe->getNomComplet(),
            'employe'       => $employe,
            'errors'        => $errors,
            'old'           => $old,
            'csrf_token_id' => 'employe_form',
        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'employe_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em,
                           EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);
        if ($employe && $employe->getIdAgriculteur() === $this->getUser()->getId()
            && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $nom = $employe->getNomComplet();
            $em->remove($employe);
            $em->flush();
            $this->addFlash('success', '🗑️ ' . $nom . ' supprimé.');
        }
        return $this->redirectToRoute('employe_index');
    }
  // ── Export PDF ────────────────────────────────────────────────────────
#[Route('/pdf', name: 'employe_pdf', methods: ['GET'])]
public function exportPdf(EmployeRepository $repo): Response
{
    $idAgriculteur = $this->getUser()->getId();
    $employes = $repo->findByAgriculteur($idAgriculteur);

    // Créer PDF
    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Infos
    $pdf->SetCreator('Ardhi');
    $pdf->SetAuthor('Ardhi');
    $pdf->SetTitle('Liste des Employés');

    // Marges
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);

    $pdf->AddPage();

    // Titre
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'LISTE DES EMPLOYÉS', 0, 1, 'C');

    // Date + total
    $pdf->SetFont('helvetica', '', 10);
    $date = (new \DateTime())->format('d/m/Y H:i');
    $total = count($employes);
    $pdf->Cell(0, 6, "Généré le : $date | Total : $total employé(s)", 0, 1, 'C');

    $pdf->Ln(5);

    // Header tableau
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(60, 90, 60);
    $pdf->SetTextColor(255, 255, 255);

    $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Nom', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Prénom', 1, 0, 'C', true);
    $pdf->Cell(60, 8, 'Email', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Poste', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Téléphone', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Actif', 1, 1, 'C', true);

    // Reset couleur texte
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);

    foreach ($employes as $emp) {
        $pdf->Cell(15, 7, $emp->getId(), 1, 0, 'C');
        $pdf->Cell(40, 7, $emp->getNom(), 1, 0, 'L');
        $pdf->Cell(40, 7, $emp->getPrenom(), 1, 0, 'L');
        $pdf->Cell(60, 7, $emp->getEmail(), 1, 0, 'L');
        $pdf->Cell(40, 7, $emp->getPoste() ?? '-', 1, 0, 'L');
        $pdf->Cell(35, 7, $emp->getTelephone() ?? '-', 1, 0, 'C');

        if ($emp->isActif()) {
            $pdf->SetFillColor(144, 238, 144);
            $pdf->Cell(20, 7, 'Oui', 1, 1, 'C', true);
        } else {
            $pdf->SetFillColor(255, 200, 200);
            $pdf->Cell(20, 7, 'Non', 1, 1, 'C', true);
        }
    }

        return new Response(
            $pdf->Output('employes_' . date('Ymd_His') . '.pdf', 'I'),
            200,
            ['Content-Type' => 'application/pdf']
        );
    }
    // ── Fiche détail ──────────────────────────────────────────────────────

    #[Route('/{id}', name: 'employe_show', methods: ['GET'])]
    public function show(int $id, EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Employé introuvable.');
        }
        return $this->render('EmployeTache/employe/show.html.twig', ['employe' => $employe]);
    }

    // ── Toggle actif/inactif ──────────────────────────────────────────────

    #[Route('/{id}/toggle', name: 'employe_toggle', methods: ['POST'])]
    public function toggle(int $id, EntityManagerInterface $em, EmployeRepository $repo): Response
    {
        $employe = $repo->find($id);
        if ($employe && $employe->getIdAgriculteur() === $this->getUser()->getId()) {
            $employe->setActif(!$employe->isActif());
            $em->flush();
            $statut = $employe->isActif() ? 'activé ✅' : 'désactivé ⛔';
            $this->addFlash('success', $employe->getNomComplet() . ' ' . $statut . '.');
        }
        return $this->redirectToRoute('employe_index');
    }

    // ══════════════════════════════════════════════════════════════════════
    // VALIDATION SERVEUR
    // ══════════════════════════════════════════════════════════════════════

    private function validerDonnees(array $data, ValidatorInterface $validator,
                                    EmployeRepository $repo, ?int $excludeId = null): array
    {
        $errors = [];

        $v = $validator->validate($data['nom'], [
            new Assert\NotBlank(message: 'Le nom est obligatoire.'),
            new Assert\Length(min: 2, max: 100,
                minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'),
            new Assert\Regex(pattern: '/^[\p{L}\s\-\']+$/u',
                message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.'),
        ]);
        if (count($v)) $errors['nom'] = $v[0]->getMessage();

        $v = $validator->validate($data['prenom'], [
            new Assert\NotBlank(message: 'Le prénom est obligatoire.'),
            new Assert\Length(min: 2, max: 100,
                minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
                maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'),
            new Assert\Regex(pattern: '/^[\p{L}\s\-\']+$/u',
                message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.'),
        ]);
        if (count($v)) $errors['prenom'] = $v[0]->getMessage();

        $v = $validator->validate($data['email'], [
            new Assert\NotBlank(message: "L'email est obligatoire."),
            new Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide."),
            new Assert\Length(max: 150, maxMessage: "L'email ne peut pas dépasser {{ limit }} caractères."),
        ]);
        if (count($v)) {
            $errors['email'] = $v[0]->getMessage();
        } elseif ($repo->emailExists($data['email'], $excludeId)) {
            $errors['email'] = 'Cet email est déjà utilisé par un autre employé.';
        }

        if ($data['poste'] !== null) {
            $v = $validator->validate($data['poste'], [
                new Assert\Length(max: 100, maxMessage: 'Le poste ne peut pas dépasser {{ limit }} caractères.')]);
            if (count($v)) $errors['poste'] = $v[0]->getMessage();
        }

        if ($data['telephone'] !== null) {
            $v = $validator->validate($data['telephone'], [
                new Assert\Length(min: 8, max: 20,
                    minMessage: 'Le téléphone doit contenir au moins {{ limit }} chiffres.',
                    maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères.'),
                new Assert\Regex(pattern: '/^[0-9\s\+\-\(\)]+$/',
                    message: 'Le téléphone ne peut contenir que des chiffres, espaces, +, - et parenthèses.'),
            ]);
            if (count($v)) $errors['telephone'] = $v[0]->getMessage();
        }

        return $errors;
    }

    private function validerPhoto(mixed $photoFile): array
    {
        if (!$photoFile) return [];
        $v = \Symfony\Component\Validator\Validation::createValidator()->validate($photoFile, [
            new Assert\File(maxSize: '5M', maxSizeMessage: 'La photo ne doit pas dépasser 5 Mo.',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                mimeTypesMessage: 'La photo doit être au format JPG, PNG ou WebP.'),
        ]);
        return count($v) ? ['photo' => $v[0]->getMessage()] : [];
    }

    private function extractFormData(Request $r): array
    {
        return [
            'nom'       => trim($r->request->get('nom', '')),
            'prenom'    => trim($r->request->get('prenom', '')),
            'email'     => strtolower(trim($r->request->get('email', ''))),
            'poste'     => $r->request->get('poste') ? trim($r->request->get('poste')) : null,
            'telephone' => $r->request->get('telephone') ? trim($r->request->get('telephone')) : null,
            'actif'     => $r->request->get('actif') === '1',
        ];
    }

    private function hydraterEmploye(Employe $employe, array $data): void
    {
        $employe->setNom($data['nom']);
        $employe->setPrenom($data['prenom']);
        $employe->setEmail($data['email']);
        $employe->setPoste($data['poste']);
        $employe->setTelephone($data['telephone']);
        $employe->setActif($data['actif']);
    }

    private function uploadPhoto(\Symfony\Component\HttpFoundation\File\UploadedFile $file,
                                  int $idEmploye): ?string
    {
        try {
            $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/employes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $filename = 'EMP_' . $idEmploye . '_' . time() . '.' . $file->guessExtension();
            $file->move($dir, $filename);
            return '/uploads/employes/' . $filename;
        } catch (\Exception) {
            return null;
        }
    }
  
}
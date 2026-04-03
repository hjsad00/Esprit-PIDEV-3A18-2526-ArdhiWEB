<?php

namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Repository\UserAndDiag\DiagnosticRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/diagnostics')]
class AdminDiagnosticController extends AbstractController
{
    #[Route('', name: 'admin_diagnostic_index')]
    public function index(DiagnosticRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Diagnostics',
            'icon' => 'bi-search',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Date Scan', 'field' => 'dateScan', 'type' => 'date'],
                ['label' => 'Résultat IA', 'field' => 'resultatIa'],
                ['label' => 'Confiance', 'field' => 'confiance'],
                ['label' => 'Sévérité', 'field' => 'severity', 'type' => 'badge', 'color' => '#dc3545'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
            ],
            'new_route' => 'admin_diagnostic_new',
            'edit_route' => 'admin_diagnostic_edit',
            'delete_route' => 'admin_diagnostic_delete',
        ]);
    }

    #[Route('/new', name: 'admin_diagnostic_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new Diagnostic();
            $this->handleForm($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Diagnostic créé.');
            return $this->redirectToRoute('admin_diagnostic_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Diagnostic',
            'fields' => $this->getFields($userRepo),
            'cancel_route' => 'admin_diagnostic_index',
            'csrf_token_id' => 'diagnostic_form',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_diagnostic_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, DiagnosticRepository $repo, UserRepository $userRepo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            $this->handleForm($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Diagnostic modifié.');
            return $this->redirectToRoute('admin_diagnostic_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Diagnostic #' . $item->getId(),
            'fields' => $this->getFields($userRepo),
            'item' => $item,
            'cancel_route' => 'admin_diagnostic_index',
            'csrf_token_id' => 'diagnostic_form',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_diagnostic_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, DiagnosticRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Diagnostic supprimé.');
        }
        return $this->redirectToRoute('admin_diagnostic_index');
    }

    private function handleForm(Request $request, Diagnostic $item, EntityManagerInterface $em, UserRepository $userRepo): void
    {
        $item->setResultatIa($request->request->get('resultat_ia') ?: null);
        $item->setConfiance($request->request->get('confiance') ? (float) $request->request->get('confiance') : null);
        $item->setSeverity($request->request->get('severity') ?: null);
        $item->setLocationLabel($request->request->get('location_label') ?: null);
        $item->setLatitude($request->request->get('latitude') ? (float) $request->request->get('latitude') : null);
        $item->setLongitude($request->request->get('longitude') ? (float) $request->request->get('longitude') : null);
        $item->setImageScannee($request->request->get('image_scannee') ?: null);
        $userId = $request->request->get('user_id');
        $item->setUser($userId ? $userRepo->find($userId) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(UserRepository $userRepo): array
    {
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        return [
            ['name' => 'resultat_ia', 'label' => 'Résultat IA', 'getter' => 'resultatIa'],
            ['name' => 'confiance', 'label' => 'Confiance', 'getter' => 'confiance', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'severity', 'label' => 'Sévérité', 'getter' => 'severity'],
            ['name' => 'location_label', 'label' => 'Localisation', 'getter' => 'locationLabel'],
            ['name' => 'latitude', 'label' => 'Latitude', 'getter' => 'latitude', 'type' => 'number', 'step' => '0.000001'],
            ['name' => 'longitude', 'label' => 'Longitude', 'getter' => 'longitude', 'type' => 'number', 'step' => '0.000001'],
            ['name' => 'image_scannee', 'label' => 'Image URL', 'getter' => 'imageScannee'],
            ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users],
        ];
    }
}

<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthScan;
use App\Repository\UserAndDiag\FarmHealthScanRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/farm-health-scans')]
class AdminFarmHealthScanController extends AbstractController
{
    #[Route('', name: 'admin_farm_health_scan_index')]
    public function index(FarmHealthScanRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Scans Santé',
            'icon' => 'bi-activity',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Culture', 'field' => 'cropType'],
                ['label' => 'Stade', 'field' => 'growthStage'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#198754'],
                ['label' => 'Score', 'field' => 'healthScore'],
                ['label' => 'Date', 'field' => 'scanDate', 'type' => 'date'],
            ],
            'new_route' => 'admin_farm_health_scan_new',
            'edit_route' => 'admin_farm_health_scan_edit',
            'delete_route' => 'admin_farm_health_scan_delete',
        ]);
    }

    #[Route('/new', name: 'admin_farm_health_scan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new FarmHealthScan();
            $this->handle($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Scan créé.');
            return $this->redirectToRoute('admin_farm_health_scan_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Scan', 'fields' => $this->getFields($userRepo), 'cancel_route' => 'admin_farm_health_scan_index', 'csrf_token_id' => 'scan_form']);
    }

    #[Route('/{id}/edit', name: 'admin_farm_health_scan_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, FarmHealthScanRepository $repo, UserRepository $userRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Scan modifié.');
            return $this->redirectToRoute('admin_farm_health_scan_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Scan #' . $item->getId(), 'fields' => $this->getFields($userRepo), 'item' => $item, 'cancel_route' => 'admin_farm_health_scan_index', 'csrf_token_id' => 'scan_form']);
    }

    #[Route('/{id}/delete', name: 'admin_farm_health_scan_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, FarmHealthScanRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Scan supprimé.');
        }
        return $this->redirectToRoute('admin_farm_health_scan_index');
    }

    private function handle(Request $r, FarmHealthScan $item, EntityManagerInterface $em, UserRepository $userRepo): void
    {
        $item->setCropType($r->request->get('crop_type', ''));
        $item->setGrowthStage($r->request->get('growth_stage', ''));
        if ($r->request->get('planting_date'))
            $item->setPlantingDate(new \DateTime($r->request->get('planting_date')));
        $item->setLatitude($r->request->get('latitude') ? (float) $r->request->get('latitude') : null);
        $item->setLongitude($r->request->get('longitude') ? (float) $r->request->get('longitude') : null);
        $item->setConcerns($r->request->get('concerns') ?: null);
        $item->setStatus($r->request->get('status') ?: 'PENDING');
        // Removed non-existent fields
        $item->setUser($r->request->get('user_id') ? $userRepo->find($r->request->get('user_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(UserRepository $userRepo): array
    {
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        return [
            ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users, 'required' => true],
            ['name' => 'crop_type', 'label' => 'Type Culture', 'getter' => 'cropType', 'required' => true],
            ['name' => 'growth_stage', 'label' => 'Stade Croissance', 'getter' => 'growthStage', 'required' => true],
            ['name' => 'planting_date', 'label' => 'Date Plantation', 'getter' => 'plantingDate', 'type' => 'date', 'required' => true],
            ['name' => 'latitude', 'label' => 'Latitude', 'getter' => 'latitude', 'type' => 'number', 'step' => '0.000001'],
            ['name' => 'longitude', 'label' => 'Longitude', 'getter' => 'longitude', 'type' => 'number', 'step' => '0.000001'],
            ['name' => 'concerns', 'label' => 'Préoccupations', 'getter' => 'concerns', 'type' => 'textarea'],
            [
                'name' => 'status',
                'label' => 'Statut',
                'getter' => 'status',
                'type' => 'select',
                'options' => [
                    ['value' => 'PENDING', 'label' => 'Pending'],
                    ['value' => 'PROCESSING', 'label' => 'Processing'],
                    ['value' => 'COMPLETED', 'label' => 'Completed'],
                    ['value' => 'FAILED', 'label' => 'Failed'],
                ]
            ],
            ['name' => 'health_score', 'label' => 'Score Santé', 'getter' => 'healthScore', 'type' => 'number', 'step' => '0.1'],
            ['name' => 'severity', 'label' => 'Sévérité', 'getter' => 'severity'],
        ];
    }
}

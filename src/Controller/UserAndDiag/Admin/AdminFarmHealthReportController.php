<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthReport;
use App\Repository\UserAndDiag\FarmHealthReportRepository;
use App\Repository\UserAndDiag\FarmHealthScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/farm-health-reports')]
class AdminFarmHealthReportController extends AbstractController
{
    #[Route('', name: 'admin_farm_health_report_index')]
    public function index(FarmHealthReportRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Rapports Santé',
            'icon' => 'bi-file-earmark-medical-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Scan', 'field' => 'scan', 'type' => 'relation'],
                ['label' => 'Score Santé', 'field' => 'healthScore'],
                ['label' => 'Score Bio', 'field' => 'biodiversityScore'],
                ['label' => 'Généré le', 'field' => 'generatedAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_farm_health_report_new',
            'edit_route' => 'admin_farm_health_report_edit',
            'delete_route' => 'admin_farm_health_report_delete',
        ]);
    }

    #[Route('/new', name: 'admin_farm_health_report_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, FarmHealthScanRepository $scanRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new FarmHealthReport();
            $this->handle($request, $item, $em, $scanRepo);
            $this->addFlash('success', 'Rapport créé.');
            return $this->redirectToRoute('admin_farm_health_report_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Rapport', 'fields' => $this->getFields($scanRepo), 'cancel_route' => 'admin_farm_health_report_index', 'csrf_token_id' => 'report_form']);
    }

    #[Route('/{id}/edit', name: 'admin_farm_health_report_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, FarmHealthReportRepository $repo, FarmHealthScanRepository $scanRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $scanRepo);
            $this->addFlash('success', 'Rapport modifié.');
            return $this->redirectToRoute('admin_farm_health_report_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Rapport #' . $item->getId(), 'fields' => $this->getFields($scanRepo), 'item' => $item, 'cancel_route' => 'admin_farm_health_report_index', 'csrf_token_id' => 'report_form']);
    }

    #[Route('/{id}/delete', name: 'admin_farm_health_report_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, FarmHealthReportRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Rapport supprimé.');
        }
        return $this->redirectToRoute('admin_farm_health_report_index');
    }

    private function handle(Request $r, FarmHealthReport $item, EntityManagerInterface $em, FarmHealthScanRepository $scanRepo): void
    {
        $item->setScan($r->request->get('scan_id') ? $scanRepo->find($r->request->get('scan_id')) : null);
        $item->setHealthScore($r->request->get('health_score') ? (int) $r->request->get('health_score') : null);
        $item->setBiodiversityScore($r->request->get('biodiversity_score') ? (int) $r->request->get('biodiversity_score') : null);
        $item->setLlavaAnalysis($r->request->get('llava_analysis') ?: null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(FarmHealthScanRepository $scanRepo): array
    {
        $scans = array_map(fn($s) => ['id' => $s->getId(), 'label' => '#' . $s->getId() . ' - ' . $s->getCropType()], $scanRepo->findAll());
        return [
            ['name' => 'scan_id', 'label' => 'Scan', 'getter' => 'scan', 'type' => 'relation_select', 'options' => $scans, 'required' => true],
            ['name' => 'health_score', 'label' => 'Score Santé', 'getter' => 'healthScore', 'type' => 'number'],
            ['name' => 'biodiversity_score', 'label' => 'Score Biodiversité', 'getter' => 'biodiversityScore', 'type' => 'number'],
            ['name' => 'llava_analysis', 'label' => 'Analyse LLaVA', 'getter' => 'llavaAnalysis', 'type' => 'textarea'],
        ];
    }
}

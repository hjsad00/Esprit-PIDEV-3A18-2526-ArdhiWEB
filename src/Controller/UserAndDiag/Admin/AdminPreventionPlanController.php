<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\PreventionPlan;
use App\Repository\UserAndDiag\PreventionPlanRepository;
use App\Repository\UserAndDiag\FarmHealthReportRepository;
use App\Repository\UserAndDiag\VulnerabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/prevention-plans')]
class AdminPreventionPlanController extends AbstractController
{
    #[Route('', name: 'admin_prevention_plan_index')]
    public function index(PreventionPlanRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Plans Prévention',
            'icon' => 'bi-shield-fill-check',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Titre', 'field' => 'title'],
                ['label' => 'Impact', 'field' => 'impactLevel', 'type' => 'badge', 'color' => '#f39c12'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#198754'],
                ['label' => 'Jours', 'field' => 'timelineDays'],
                ['label' => 'Coût', 'field' => 'estimatedCost'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_prevention_plan_new',
            'edit_route' => 'admin_prevention_plan_edit',
            'delete_route' => 'admin_prevention_plan_delete',
        ]);
    }

    #[Route('/new', name: 'admin_prevention_plan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, FarmHealthReportRepository $reportRepo, VulnerabilityRepository $vulnRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new PreventionPlan();
            $this->handle($request, $item, $em, $reportRepo, $vulnRepo);
            $this->addFlash('success', 'Plan créé.');
            return $this->redirectToRoute('admin_prevention_plan_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Plan Prévention', 'fields' => $this->getFields($reportRepo, $vulnRepo), 'cancel_route' => 'admin_prevention_plan_index', 'csrf_token_id' => 'pp_form']);
    }

    #[Route('/{id}/edit', name: 'admin_prevention_plan_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, PreventionPlanRepository $repo, FarmHealthReportRepository $reportRepo, VulnerabilityRepository $vulnRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $reportRepo, $vulnRepo);
            $this->addFlash('success', 'Plan modifié.');
            return $this->redirectToRoute('admin_prevention_plan_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Plan #' . $item->getId(), 'fields' => $this->getFields($reportRepo, $vulnRepo), 'item' => $item, 'cancel_route' => 'admin_prevention_plan_index', 'csrf_token_id' => 'pp_form']);
    }

    #[Route('/{id}/delete', name: 'admin_prevention_plan_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, PreventionPlanRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Plan supprimé.');
        }
        return $this->redirectToRoute('admin_prevention_plan_index');
    }

    private function handle(Request $r, PreventionPlan $item, EntityManagerInterface $em, FarmHealthReportRepository $reportRepo, VulnerabilityRepository $vulnRepo): void
    {
        $item->setTitle($r->request->get('title', ''));
        $item->setProblemSummary($r->request->get('problem_summary') ?: null);
        $item->setSteps($r->request->get('steps', ''));
        $item->setTimelineDays($r->request->get('timeline_days') ? (int) $r->request->get('timeline_days') : null);
        $item->setEstimatedCost($r->request->get('estimated_cost') ? (float) $r->request->get('estimated_cost') : null);
        $item->setExpectedOutcome($r->request->get('expected_outcome') ?: null);
        $item->setImpactLevel($r->request->get('impact_level') ?: null);
        $item->setStatus($r->request->get('status') ?: 'ACTIVE');
        if ($r->request->get('start_date'))
            $item->setStartDate(new \DateTime($r->request->get('start_date')));
        $item->setReport($r->request->get('report_id') ? $reportRepo->find($r->request->get('report_id')) : null);
        $item->setVulnerability($r->request->get('vulnerability_id') ? $vulnRepo->find($r->request->get('vulnerability_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(FarmHealthReportRepository $reportRepo, VulnerabilityRepository $vulnRepo): array
    {
        $reports = array_map(fn($r) => ['id' => $r->getId(), 'label' => '#' . $r->getId()], $reportRepo->findAll());
        $vulns = array_map(fn($v) => ['id' => $v->getId(), 'label' => '#' . $v->getId() . ' - ' . $v->getThreat()], $vulnRepo->findAll());
        return [
            ['name' => 'title', 'label' => 'Titre', 'getter' => 'title', 'required' => true],
            ['name' => 'problem_summary', 'label' => 'Résumé Problème', 'getter' => 'problemSummary', 'type' => 'textarea'],
            ['name' => 'steps', 'label' => 'Étapes', 'getter' => 'steps', 'type' => 'textarea', 'required' => true],
            ['name' => 'timeline_days', 'label' => 'Durée (jours)', 'getter' => 'timelineDays', 'type' => 'number'],
            ['name' => 'estimated_cost', 'label' => 'Coût Estimé', 'getter' => 'estimatedCost', 'type' => 'number', 'step' => '0.01'],
            ['name' => 'expected_outcome', 'label' => 'Résultat Attendu', 'getter' => 'expectedOutcome', 'type' => 'textarea'],
            ['name' => 'impact_level', 'label' => 'Impact', 'getter' => 'impactLevel', 'type' => 'select', 'options' => [['value' => 'HIGH', 'label' => 'High'], ['value' => 'MEDIUM', 'label' => 'Medium'], ['value' => 'LOW', 'label' => 'Low']]],
            ['name' => 'status', 'label' => 'Statut', 'getter' => 'status', 'type' => 'select', 'options' => [['value' => 'ACTIVE', 'label' => 'Active'], ['value' => 'COMPLETED', 'label' => 'Completed'], ['value' => 'ABANDONED', 'label' => 'Abandoned']]],
            ['name' => 'start_date', 'label' => 'Date Début', 'getter' => 'startDate', 'type' => 'date'],
            ['name' => 'report_id', 'label' => 'Rapport', 'getter' => 'report', 'type' => 'relation_select', 'options' => $reports, 'required' => true],
            ['name' => 'vulnerability_id', 'label' => 'Vulnérabilité', 'getter' => 'vulnerability', 'type' => 'relation_select', 'options' => $vulns],
        ];
    }
}

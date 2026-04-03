<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Review;
use App\Repository\UserAndDiag\ReviewRepository;
use App\Repository\UserAndDiag\DiagnosticRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reviews')]
class AdminReviewController extends AbstractController
{
    #[Route('', name: 'admin_review_index')]
    public function index(ReviewRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Reviews',
            'icon' => 'bi-clipboard-check-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Type', 'field' => 'reviewType', 'type' => 'badge', 'color' => '#6610f2'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#198754'],
                ['label' => 'Expert', 'field' => 'expert', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Verdict', 'field' => 'expertVerdict'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_review_new',
            'edit_route' => 'admin_review_edit',
            'delete_route' => 'admin_review_delete',
        ]);
    }

    #[Route('/new', name: 'admin_review_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, DiagnosticRepository $diagRepo, UserRepository $userRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new Review();
            $this->handle($request, $item, $em, $diagRepo, $userRepo);
            $this->addFlash('success', 'Review créée.');
            return $this->redirectToRoute('admin_review_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouvelle Review', 'fields' => $this->getFields($diagRepo, $userRepo), 'cancel_route' => 'admin_review_index', 'csrf_token_id' => 'review_form']);
    }

    #[Route('/{id}/edit', name: 'admin_review_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, ReviewRepository $repo, DiagnosticRepository $diagRepo, UserRepository $userRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $diagRepo, $userRepo);
            $this->addFlash('success', 'Review modifiée.');
            return $this->redirectToRoute('admin_review_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Review #' . $item->getId(), 'fields' => $this->getFields($diagRepo, $userRepo), 'item' => $item, 'cancel_route' => 'admin_review_index', 'csrf_token_id' => 'review_form']);
    }

    #[Route('/{id}/delete', name: 'admin_review_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, ReviewRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Review supprimée.');
        }
        return $this->redirectToRoute('admin_review_index');
    }

    private function handle(Request $r, Review $item, EntityManagerInterface $em, DiagnosticRepository $diagRepo, UserRepository $userRepo): void
    {
        $item->setReviewType($r->request->get('review_type', 'DIAGNOSIS'));
        $item->setStatus($r->request->get('status') ?: 'PENDING');
        $item->setExpertNotes($r->request->get('expert_notes') ?: null);
        $item->setExpertVerdict($r->request->get('expert_verdict') ?: null);
        $item->setExpertDiseaseName($r->request->get('expert_disease_name') ?: null);
        $item->setAiAnalysis($r->request->get('ai_analysis') ?: null);
        $item->setPhotoUrl($r->request->get('photo_url') ?: null);
        $item->setDiagnostic($r->request->get('diagnostic_id') ? $diagRepo->find($r->request->get('diagnostic_id')) : null);
        $item->setExpert($r->request->get('expert_id') ? $userRepo->find($r->request->get('expert_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(DiagnosticRepository $diagRepo, UserRepository $userRepo): array
    {
        $diags = array_map(fn($d) => ['id' => $d->getId(), 'label' => '#' . $d->getId() . ' - ' . ($d->getResultatIa() ?: 'N/A')], $diagRepo->findAll());
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        return [
            ['name' => 'diagnostic_id', 'label' => 'Diagnostic', 'getter' => 'diagnostic', 'type' => 'relation_select', 'options' => $diags, 'required' => true],
            [
                'name' => 'review_type',
                'label' => 'Type',
                'getter' => 'reviewType',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'DIAGNOSIS', 'label' => 'Diagnosis'],
                    ['value' => 'PROGRESS', 'label' => 'Progress'],
                    ['value' => 'PREVENTION', 'label' => 'Prevention'],
                ]
            ],
            [
                'name' => 'status',
                'label' => 'Statut',
                'getter' => 'status',
                'type' => 'select',
                'options' => [
                    ['value' => 'PENDING', 'label' => 'Pending'],
                    ['value' => 'IN_PROGRESS', 'label' => 'In Progress'],
                    ['value' => 'COMPLETED', 'label' => 'Completed'],
                ]
            ],
            ['name' => 'expert_id', 'label' => 'Expert', 'getter' => 'expert', 'type' => 'relation_select', 'options' => $users],
            ['name' => 'expert_notes', 'label' => 'Notes Expert', 'getter' => 'expertNotes', 'type' => 'textarea'],
            [
                'name' => 'expert_verdict',
                'label' => 'Verdict',
                'getter' => 'expertVerdict',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => '--'],
                    ['value' => 'CONTINUE', 'label' => 'Continue'],
                    ['value' => 'HEALED', 'label' => 'Healed'],
                    ['value' => 'WORSENED', 'label' => 'Worsened'],
                ]
            ],
            ['name' => 'expert_disease_name', 'label' => 'Nom Maladie', 'getter' => 'expertDiseaseName'],
            ['name' => 'ai_analysis', 'label' => 'Analyse IA', 'getter' => 'aiAnalysis', 'type' => 'textarea'],
            ['name' => 'photo_url', 'label' => 'Photo URL', 'getter' => 'photoUrl'],
        ];
    }
}

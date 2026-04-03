<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Traitement;
use App\Repository\UserAndDiag\TraitementRepository;
use App\Repository\UserAndDiag\DiagnosticRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/traitements')]
class AdminTraitementController extends AbstractController
{
    #[Route('', name: 'admin_traitement_index')]
    public function index(TraitementRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Traitements',
            'icon' => 'bi-capsule',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Solution', 'field' => 'solutionNom'],
                ['label' => 'Type', 'field' => 'typeTraitement', 'type' => 'badge', 'color' => '#e74c3c'],
                ['label' => 'Durée', 'field' => 'dureeRecommandee'],
                ['label' => 'Diagnostic', 'field' => 'diagnostic', 'type' => 'relation'],
            ],
            'new_route' => 'admin_traitement_new',
            'edit_route' => 'admin_traitement_edit',
            'delete_route' => 'admin_traitement_delete',
        ]);
    }

    #[Route('/new', name: 'admin_traitement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, DiagnosticRepository $diagRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new Traitement();
            $this->handle($request, $item, $em, $diagRepo);
            $this->addFlash('success', 'Traitement créé.');
            return $this->redirectToRoute('admin_traitement_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Traitement', 'fields' => $this->getFields($diagRepo), 'cancel_route' => 'admin_traitement_index', 'csrf_token_id' => 'traitement_form']);
    }

    #[Route('/{id}/edit', name: 'admin_traitement_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, TraitementRepository $repo, DiagnosticRepository $diagRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $diagRepo);
            $this->addFlash('success', 'Traitement modifié.');
            return $this->redirectToRoute('admin_traitement_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Traitement #' . $item->getId(), 'fields' => $this->getFields($diagRepo), 'item' => $item, 'cancel_route' => 'admin_traitement_index', 'csrf_token_id' => 'traitement_form']);
    }

    #[Route('/{id}/delete', name: 'admin_traitement_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, TraitementRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Traitement supprimé.');
        }
        return $this->redirectToRoute('admin_traitement_index');
    }

    private function handle(Request $r, Traitement $item, EntityManagerInterface $em, DiagnosticRepository $diagRepo): void
    {
        $item->setSolutionNom($r->request->get('solution_nom', ''));
        $item->setDescriptionDetaillee($r->request->get('description_detaillee') ?: null);
        $item->setTypeTraitement($r->request->get('type_traitement') ?: 'AUTRE');
        $item->setDureeRecommandee($r->request->get('duree_recommandee') ?: null);
        $item->setDiagnostic($r->request->get('diagnostic_id') ? $diagRepo->find($r->request->get('diagnostic_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(DiagnosticRepository $diagRepo): array
    {
        $diags = array_map(fn($d) => ['id' => $d->getId(), 'label' => '#' . $d->getId()], $diagRepo->findAll());
        return [
            ['name' => 'solution_nom', 'label' => 'Nom Solution', 'getter' => 'solutionNom', 'required' => true],
            ['name' => 'description_detaillee', 'label' => 'Description', 'getter' => 'descriptionDetaillee', 'type' => 'textarea'],
            [
                'name' => 'type_traitement',
                'label' => 'Type',
                'getter' => 'typeTraitement',
                'type' => 'select',
                'options' => [
                    ['value' => 'FONGICIDE', 'label' => 'Fongicide'],
                    ['value' => 'HERBICIDE', 'label' => 'Herbicide'],
                    ['value' => 'INSECTICIDE', 'label' => 'Insecticide'],
                    ['value' => 'BACTERICIDE', 'label' => 'Bactéricide'],
                    ['value' => 'NEMATICIDE', 'label' => 'Nématicide'],
                    ['value' => 'VIRUCIDE', 'label' => 'Virucide'],
                    ['value' => 'NUTRIMENT', 'label' => 'Nutriment'],
                    ['value' => 'REGULATEUR_CROISSANCE', 'label' => 'Régulateur Croissance'],
                    ['value' => 'AUTRE', 'label' => 'Autre'],
                ]
            ],
            ['name' => 'duree_recommandee', 'label' => 'Durée Recommandée', 'getter' => 'dureeRecommandee'],
            ['name' => 'diagnostic_id', 'label' => 'Diagnostic', 'getter' => 'diagnostic', 'type' => 'relation_select', 'options' => $diags, 'required' => true],
        ];
    }
}

<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Traitement;
use App\Form\UserAndDiag\Admin\AdminTraitementType;
use App\Repository\UserAndDiag\TraitementRepository;
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
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new Traitement();
        $form = $this->createForm(AdminTraitementType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Traitement créé.');
            return $this->redirectToRoute('admin_traitement_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Traitement',
            'form' => $form,
            'cancel_route' => 'admin_traitement_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_traitement_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, TraitementRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminTraitementType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Traitement modifié.');
            return $this->redirectToRoute('admin_traitement_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Traitement #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_traitement_index',
        ]);
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
}

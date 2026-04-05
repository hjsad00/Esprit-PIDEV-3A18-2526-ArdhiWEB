<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Offre;
use App\Form\UserAndDiag\Admin\AdminOffreType;
use App\Repository\UserAndDiag\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/offres')]
class AdminOffreController extends AbstractController
{
    #[Route('', name: 'admin_offre_index')]
    public function index(OffreRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Offres',
            'icon' => 'bi-tag-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Nom', 'field' => 'nom'],
                ['label' => 'Prix/mois', 'field' => 'prixMensuel'],
                ['label' => 'Active', 'field' => 'estActive', 'type' => 'bool'],
                ['label' => 'Recommandée', 'field' => 'estRecommandee', 'type' => 'bool'],
                ['label' => 'Diag/h', 'field' => 'diagnosticsParHeure'],
            ],
            'new_route' => 'admin_offre_new',
            'edit_route' => 'admin_offre_edit',
            'delete_route' => 'admin_offre_delete',
        ]);
    }

    #[Route('/new', name: 'admin_offre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new Offre();
        $form = $this->createForm(AdminOffreType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Offre créée.');
            return $this->redirectToRoute('admin_offre_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvelle Offre',
            'form' => $form,
            'cancel_route' => 'admin_offre_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_offre_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, OffreRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminOffreType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Offre modifiée.');
            return $this->redirectToRoute('admin_offre_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Offre #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_offre_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_offre_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, OffreRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Offre supprimée.');
        }
        return $this->redirectToRoute('admin_offre_index');
    }
}

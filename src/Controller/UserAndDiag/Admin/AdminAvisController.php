<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Avis;
use App\Repository\UserAndDiag\AvisRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/avis')]
class AdminAvisController extends AbstractController
{
    #[Route('', name: 'admin_avis_index')]
    public function index(AvisRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Avis',
            'icon' => 'bi-star-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'idAvis'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'ID Produit', 'field' => 'idProduit'],
                ['label' => 'Note', 'field' => 'note'],
                ['label' => 'Commentaire', 'field' => 'commentaire', 'type' => 'truncate'],
                ['label' => 'Date', 'field' => 'dateAvis', 'type' => 'date'],
            ],
            'id_getter' => 'idAvis',
            'new_route' => 'admin_avis_new',
            'edit_route' => 'admin_avis_edit',
            'delete_route' => 'admin_avis_delete',
        ]);
    }

    #[Route('/new', name: 'admin_avis_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new Avis();
            $this->handleForm($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Avis créé.');
            return $this->redirectToRoute('admin_avis_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvel Avis',
            'fields' => $this->getFields($userRepo),
            'cancel_route' => 'admin_avis_index',
            'csrf_token_id' => 'avis_form',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_avis_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, AvisRepository $repo, UserRepository $userRepo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            $this->handleForm($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Avis modifié.');
            return $this->redirectToRoute('admin_avis_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Avis #' . $item->getIdAvis(),
            'fields' => $this->getFields($userRepo),
            'item' => $item,
            'cancel_route' => 'admin_avis_index',
            'csrf_token_id' => 'avis_form',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_avis_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, AvisRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Avis supprimé.');
        }
        return $this->redirectToRoute('admin_avis_index');
    }

    private function handleForm(Request $r, Avis $item, EntityManagerInterface $em, UserRepository $userRepo): void
    {
        $item->setUser($r->request->get('user_id') ? $userRepo->find($r->request->get('user_id')) : null);
        $item->setIdProduit((int) $r->request->get('id_produit', 0));
        $item->setNote((int) $r->request->get('note', 0));
        $item->setCommentaire($r->request->get('commentaire') ?: null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(UserRepository $userRepo): array
    {
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        return [
            ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users, 'required' => true],
            ['name' => 'id_produit', 'label' => 'ID Produit', 'getter' => 'idProduit', 'type' => 'number', 'required' => true],
            ['name' => 'note', 'label' => 'Note (1-5)', 'getter' => 'note', 'type' => 'number', 'required' => true],
            ['name' => 'commentaire', 'label' => 'Commentaire', 'getter' => 'commentaire', 'type' => 'textarea'],
        ];
    }
}

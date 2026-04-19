<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\User;
use App\Form\UserAndDiag\Admin\AdminUserType;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_user_index')]
    public function index(Request $request, UserRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'id');
        $direction = $request->query->get('direction', 'asc');

        $qb = $repo->createQueryBuilder('u');

        if ($q) {
            $qb->andWhere('u.email LIKE :q OR u.nom LIKE :q OR u.prenom LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // Validate sort field to prevent SQL injection or errors
        $allowedFields = ['id', 'email', 'nom', 'prenom', 'points', 'level', 'phone'];
        if (in_array($sort, $allowedFields)) {
            $qb->orderBy('u.' . $sort, $direction);
        } else {
            $qb->orderBy('u.id', 'DESC');
        }

        $items = $qb->getQuery()->getResult();

        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Utilisateurs',
            'icon' => 'bi-people-fill',
            'items' => $items,
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Email', 'field' => 'email'],
                ['label' => 'Nom', 'field' => 'nom'],
                ['label' => 'Prénom', 'field' => 'prenom'],
                ['label' => 'Rôle', 'field' => 'role', 'type' => 'badge', 'color' => '#116530'],
                ['label' => 'Points', 'field' => 'points'],
                ['label' => 'Niveau', 'field' => 'level'],
                ['label' => 'Téléphone', 'field' => 'phone'],
            ],
            'new_route' => 'admin_user_new',
            'edit_route' => 'admin_user_edit',
            'delete_route' => 'admin_user_delete',
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvel Utilisateur',
            'form' => $form,
            'cancel_route' => 'admin_user_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, UserRepository $repo, UserPasswordHasherInterface $hasher): Response
    {
        $user = $repo->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminUserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }
            $em->flush();
            $this->addFlash('success', 'Utilisateur modifié.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Utilisateur #' . $user->getId(),
            'form' => $form,
            'cancel_route' => 'admin_user_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, UserRepository $repo): Response
    {
        $user = $repo->find($id);
        if ($user && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }
        return $this->redirectToRoute('admin_user_index');
    }
}

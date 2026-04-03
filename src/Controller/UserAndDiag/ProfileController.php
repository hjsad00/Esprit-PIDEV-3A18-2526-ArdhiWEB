<?php

namespace App\Controller\UserAndDiag;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('profile_edit', $csrfToken)) {
                $this->addFlash('danger', 'Token CSRF invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_profile');
            }

            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $phone = trim($request->request->get('phone', ''));
            $location = trim($request->request->get('location', ''));
            $role = trim($request->request->get('role', ''));
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');

            $errors = [];

            if (empty($nom)) {
                $errors[] = 'Le nom est obligatoire.';
            }
            if (empty($prenom)) {
                $errors[] = 'Le prénom est obligatoire.';
            }
            if (!in_array($role, ['AGRICULTEUR', 'CLIENT', 'AGRONOME'])) {
                $errors[] = 'Le rôle sélectionné est invalide.';
            }

            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
                }
                if ($password !== $passwordConfirm) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                }
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }
            } else {
                $user->setNom($nom);
                $user->setPrenom($prenom);
                $user->setPhone($phone ?: null);
                $user->setLocation($location ?: null);
                $user->setRole($role);

                if (!empty($password)) {
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                }

                $entityManager->flush();
                $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('UserAndDiag/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $csrfToken = $request->request->get('_token', '');

        if ($this->isCsrfTokenValid('delete_account', $csrfToken)) {
            // Log out the user by invalidating the session and token storage
            $request->getSession()->invalidate();
            if ($this->container->has('security.token_storage')) {
                $this->container->get('security.token_storage')->setToken(null);
            }

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('danger', 'Token CSRF invalide.');
        return $this->redirectToRoute('app_profile');
    }
}

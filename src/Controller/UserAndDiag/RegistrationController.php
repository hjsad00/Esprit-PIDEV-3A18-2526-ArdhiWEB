<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\User;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security,
    ): Response {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $email = trim($request->request->get('email', ''));
            $role = trim($request->request->get('role', 'AGRICULTEUR'));
            $phone = trim($request->request->get('phone', ''));
            $location = trim($request->request->get('location', ''));
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');
            $csrfToken = $request->request->get('_csrf_token', '');

            // CSRF check
            if (!$this->isCsrfTokenValid('register', $csrfToken)) {
                $this->addFlash('danger', 'Token CSRF invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_register');
            }

            // Validation
            $errors = [];

            if (empty($nom)) {
                $errors[] = 'Le nom est obligatoire.';
            }
            if (empty($prenom)) {
                $errors[] = 'Le prénom est obligatoire.';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Veuillez entrer une adresse email valide.';
            }
            if (strlen($password) < 6) {
                $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
            }
            if ($password !== $passwordConfirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }
            if (!in_array($role, ['AGRICULTEUR', 'CLIENT', 'AGRONOME'])) {
                $errors[] = 'Le rôle sélectionné est invalide.';
            }

            // Check email uniqueness
            if (empty($errors) && $userRepository->findOneBy(['email' => $email])) {
                $errors[] = 'Un compte existe déjà avec cet email.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }

                return $this->render('UserAndDiag/register.html.twig', [
                    'last_nom' => $nom,
                    'last_prenom' => $prenom,
                    'last_email' => $email,
                    'last_role' => $role,
                    'last_phone' => $phone,
                    'last_location' => $location,
                ]);
            }

            // Create user
            $user = new User();
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setEmail($email);
            $user->setPhone($phone ?: null);
            $user->setLocation($location ?: null);
            $user->setRole($role);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $entityManager->persist($user);
            $entityManager->flush();

            // Auto-login after registration
            $security->login($user, 'form_login', 'main');

            $this->addFlash('success', 'Inscription réussie ! Bienvenue ' . $prenom . ' !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('UserAndDiag/register.html.twig', [
            'last_nom' => '',
            'last_prenom' => '',
            'last_email' => '',
            'last_role' => 'AGRICULTEUR',
            'last_phone' => '',
            'last_location' => '',
        ]);
    }
}

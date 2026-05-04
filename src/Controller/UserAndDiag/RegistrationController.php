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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security,
        ValidatorInterface $validator,
    ): Response {
        // Redirect if already fully logged in
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
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
            $bio = trim($request->request->get('bio', ''));
            $csrfToken = $request->request->get('_csrf_token', '');

            // CSRF check
            if (!$this->isCsrfTokenValid('register', $csrfToken)) {
                $this->addFlash('danger', 'Token CSRF invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_register');
            }

            // reCAPTCHA check
            $recaptchaToken = $request->request->get('g-recaptcha-response');
            if (empty($recaptchaToken)) {
                $this->addFlash('danger', 'Échec de validation reCAPTCHA (Jeton vide).');
                return $this->redirectToRoute('app_register');
            }

            $secret = '6Lealb4sAAAAAMv5UN8i9JLYwq26SCpwtFKFTGAp';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'secret' => $secret,
                'response' => $recaptchaToken
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $verifyResponse = curl_exec($ch);
            curl_close($ch);

            $data = json_decode((string) $verifyResponse, true);

            if (empty($data['success'])) {
                $this->addFlash('danger', 'Veuillez cocher la case reCAPTCHA.');
                return $this->redirectToRoute('app_register');
            }

            // Email MX + SMTP mailbox verification — block non-existent emails
            $emailDomain = substr(strrchr($email, '@'), 1);
            $emailInvalid = false;
            $emailError = '';

            if (!checkdnsrr($emailDomain, 'MX')) {
                $emailInvalid = true;
                $emailError = 'Le domaine "' . $emailDomain . '" n\'existe pas ou n\'accepte pas les emails.';
            } else {
                // SMTP probe to check if the specific mailbox exists
                $mxHosts = [];
                getmxrr($emailDomain, $mxHosts);
                if (!empty($mxHosts)) {
                    $socket = @fsockopen($mxHosts[0], 25, $errno, $errstr, 5);
                    if ($socket) {
                        fgets($socket, 1024);
                        fputs($socket, "HELO ardhi.local\r\n");
                        fgets($socket, 1024);
                        fputs($socket, "MAIL FROM:<verify@ardhi.local>\r\n");
                        fgets($socket, 1024);
                        fputs($socket, "RCPT TO:<{$email}>\r\n");
                        $rcptResponse = fgets($socket, 1024);
                        fputs($socket, "QUIT\r\n");
                        fclose($socket);

                        $smtpCode = (int) substr(trim($rcptResponse), 0, 3);
                        if ($smtpCode === 550 || $smtpCode === 551 || $smtpCode === 553) {
                            $emailInvalid = true;
                            $emailError = 'Cette adresse email n\'existe pas.';
                        }
                    }
                }
            }

            if ($emailInvalid) {
                return $this->render('UserAndDiag/register.html.twig', [
                    'last_nom' => $nom,
                    'last_prenom' => $prenom,
                    'last_email' => $email,
                    'last_role' => $role,
                    'last_phone' => $phone,
                    'last_location' => $location,
                    'last_bio' => $bio,
                    'errors' => ['email' => $emailError],
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // Create user object for validation
            $user = new User();
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setEmail($email);
            $user->setRole($role);
            $user->setPhone($phone ?: null);
            $user->setLocation($location ?: null);
            $user->setBio($bio ?: null);
            $user->setPassword($password); // Plain password for validation
            $user->setPasswordConfirm($passwordConfirm);

            // Validate the entity
            $violationList = $validator->validate($user, null, ['registration', 'Default']);

            $errors = [];
            if (count($violationList) > 0) {
                foreach ($violationList as $violation) {
                    $path = $violation->getPropertyPath();
                    $errors[$path] = $violation->getMessage();
                }
            }

            if (!empty($errors)) {
                return $this->render('UserAndDiag/register.html.twig', [
                    'last_nom' => $nom,
                    'last_prenom' => $prenom,
                    'last_email' => $email,
                    'last_role' => $role,
                    'last_phone' => $phone,
                    'last_location' => $location,
                    'last_bio' => $bio,
                    'errors' => $errors,
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // If valid, hash password and save
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
            'last_bio' => '',
            'errors' => [],
        ]);
    }

    // ──── Email Verification: MX + SMTP Probe ────
    #[Route('/register/verify-email', name: 'app_register_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['valid' => false, 'reason' => 'Format email invalide.']);
        }

        $domain = substr(strrchr($email, '@'), 1);

        // Check MX records
        if (!checkdnsrr($domain, 'MX')) {
            return $this->json(['valid' => false, 'reason' => 'Le domaine "' . $domain . '" n\'accepte pas les emails.']);
        }

        // Get MX host
        $mxHosts = [];
        getmxrr($domain, $mxHosts);
        if (empty($mxHosts)) {
            return $this->json(['valid' => false, 'reason' => 'Aucun serveur de messagerie trouvé pour ce domaine.']);
        }

        // Try SMTP probe
        $mxHost = $mxHosts[0];
        $socket = @fsockopen($mxHost, 25, $errno, $errstr, 5);

        if (!$socket) {
            // Port 25 blocked (common on Windows/ISPs) — but MX exists so domain is valid
            return $this->json(['valid' => true, 'reason' => 'Domaine email valide (serveur MX trouvé).']);
        }

        $response = fgets($socket, 1024);
        fputs($socket, "HELO ardhi.local\r\n");
        $response = fgets($socket, 1024);
        fputs($socket, "MAIL FROM:<verify@ardhi.local>\r\n");
        $response = fgets($socket, 1024);
        fputs($socket, "RCPT TO:<{$email}>\r\n");
        $rcptResponse = fgets($socket, 1024);
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        // 250 = accepted, 550 = mailbox doesn't exist
        $code = (int) substr(trim($rcptResponse), 0, 3);

        if ($code === 250) {
            return $this->json(['valid' => true, 'reason' => 'Email vérifié et valide !']);
        } elseif ($code === 550 || $code === 551 || $code === 553) {
            return $this->json(['valid' => false, 'reason' => 'Cette boîte mail n\'existe pas.']);
        } else {
            // Some servers don't allow RCPT probing but MX exists
            return $this->json(['valid' => true, 'reason' => 'Domaine email valide (vérification avancée bloquée par le serveur).']);
        }
    }

    // ──── Twilio SMS: Send OTP ────
    #[Route('/register/send-sms', name: 'app_register_send_sms', methods: ['POST'])]
    public function sendSms(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $phone = $data['phone'] ?? '';

        if (empty($phone)) {
            return $this->json(['success' => false, 'error' => 'Numéro manquant.']);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store code in session for verification
        $session = $request->getSession();
        $session->set('sms_otp_code', $code);
        $session->set('sms_otp_phone', $phone);

        // Twilio API call
        $twilioSid = 'AC9c3432ef37f1587d3c5aa66874381487';
        $twilioToken = '20ad44cd17c2b3de97087777dc451f58';
        $twilioFrom = '+13527902472'; // Your Twilio number

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'To' => $phone,
            'From' => $twilioFrom,
            'Body' => "Votre code de vérification Ardhi : {$code}"
        ]));
        curl_setopt($ch, CURLOPT_USERPWD, "{$twilioSid}:{$twilioToken}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $this->json(['success' => true]);
        }

        // Fallback: still store code so it can be tested locally
        return $this->json(['success' => true, 'debug' => 'Twilio non configuré, code stocké en session: ' . $code]);
    }

    // ──── Twilio SMS: Verify OTP ────
    #[Route('/register/verify-sms', name: 'app_register_verify_sms', methods: ['POST'])]
    public function verifySms(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? '';
        $phone = $data['phone'] ?? '';

        $session = $request->getSession();
        $storedCode = $session->get('sms_otp_code');
        $storedPhone = $session->get('sms_otp_phone');

        if ($code === $storedCode && $phone === $storedPhone) {
            $session->remove('sms_otp_code');
            $session->remove('sms_otp_phone');
            return $this->json(['success' => true]);
        }

        return $this->json(['success' => false, 'error' => 'Code incorrect.']);
    }
}

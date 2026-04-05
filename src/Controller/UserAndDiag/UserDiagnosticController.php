<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\Diagnostic;
use App\Entity\UserAndDiag\Traitement;
use App\Service\UserAndDiag\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/diagnostic')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserDiagnosticController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_user_diagnostic', methods: ['GET'])]
    public function index(
        \App\Service\UserAndDiag\SubscriptionFeatureService $featureService,
        \App\Repository\UserAndDiag\AbonnementRepository $aboRepo
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $features = $featureService->getFeatures($user);
        $usageCount = $featureService->getDiagnosticUsageCount($user);
        $abo = $aboRepo->findActiveByUser($user);
        $planName = $abo ? $abo->getType() : 'Gratuit';

        // Render the new diagnostic scanning UI
        return $this->render('UserAndDiag/user_diagnostic/index.html.twig', [
            'user' => $user,
            'limit' => $features['diagnosticsParHeure'],
            'used' => $usageCount,
            'planName' => $planName
        ]);
    }

    #[Route('/scan', name: 'app_user_and_diag_user_diagnostic_scan', methods: ['POST'])]
    public function scan(
        Request $request,
        GroqService $groqService,
        \App\Service\UserAndDiag\LocationService $locationService,
        EntityManagerInterface $entityManager,
        \App\Service\UserAndDiag\SubscriptionFeatureService $featureService,
        \App\Service\UserAndDiag\GamificationService $gamificationService,
        \App\Service\UserAndDiag\ImgBBService $imgBBService
    ): Response {
        // ... (lines 50-156 are unchanged, but I must provide the exact identical content for the replacement to work!)
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // 1. Check Rate Limits
        if (!$featureService->canPerformDiagnostic($user)) {
            return $this->json(['error' => 'Vous avez atteint votre limite de diagnostics par heure. Veuillez mettre à niveau votre abonnement.'], 403);
        }

        // 2. Handle uploaded image
        /** @var UploadedFile $file */
        $file = $request->files->get('image');

        if (!$file) {
            return $this->json(['error' => 'Aucune image n\'a été fournie.'], 400);
        }

        // Check if file is an image
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
            return $this->json(['error' => 'Veuillez télécharger une image valide (JPG, PNG, WEBP).'], 400);
        }

        try {
            // 3. Query Groq AI Model
            $response = $groqService->analyserImage($file);

            if (str_starts_with($response, 'ERREUR')) {
                return $this->json(['error' => "L'IA a rencontré une erreur: " . $response], 500);
            }

            // 4. Parse Response: PLANTE|MALADIE|CONFIANCE|NOM_PRODUIT|TYPE_TRAITEMENT|DESCRIPTION_DOSAGE_ET_APPLICATION|SEVERITY
            $parts = explode('|', str_replace('```', '', trim($response)));
            if (count($parts) < 6) {
                return $this->json(['error' => 'Format de réponse IA invalide. Essayez avec une photo plus claire.'], 500);
            }

            $plante = trim($parts[0]);
            $maladie = trim($parts[1]);

            // Extract numbers from confidence
            $confString = preg_replace('/[^0-9.]/', '', trim($parts[2]));
            $confiance = is_numeric($confString) ? (float) $confString : 50.0;

            $traitementNom = trim($parts[3]);

            $typeTraitement = strtoupper(trim($parts[4]));
            if (strlen($typeTraitement) > 50) {
                $typeTraitement = substr($typeTraitement, 0, 50);
            }
            $validTypes = ['FONGICIDE', 'HERBICIDE', 'INSECTICIDE', 'BACTERICIDE', 'NEMATICIDE', 'VIRUCIDE', 'NUTRIMENT', 'REGULATEUR_CROISSANCE', 'AUTRE'];
            if (!in_array($typeTraitement, $validTypes)) {
                $typeTraitement = 'AUTRE';
            }

            $description = trim($parts[5]);
            $severity = isset($parts[6]) ? strtoupper(trim($parts[6])) : ($confiance >= 80 ? 'CRITICAL' : ($confiance >= 50 ? 'MEDIUM' : 'LOW'));
            if (!in_array($severity, ['CRITICAL', 'MEDIUM', 'LOW'])) {
                $severity = $confiance >= 80 ? 'CRITICAL' : ($confiance >= 50 ? 'MEDIUM' : 'LOW');
            }

            // 4. Upload image to ImgBB
            $publicPath = $imgBBService->uploadImage($file);
            if (!$publicPath) {
                return $this->json(['error' => 'Échec de l\'upload de l\'image. Veuillez réessayer.'], 500);
            }

            // 5. Persist Diagnostic
            $diagnostic = new Diagnostic();
            $diagnostic->setUser($this->getUser());
            $diagnostic->setImageScannee($publicPath);
            $diagnostic->setResultatIa($plante . ' - ' . $maladie);
            $diagnostic->setConfiance($confiance);
            $diagnostic->setSeverity($severity);

            // Extract location dynamically via IP fallback (mimicking Java desktop app)
            $ipData = $locationService->detectLocation($request->getClientIp());
            if ($ipData) {
                $latVal = $ipData['latitude'];
                $lngVal = $ipData['longitude'];
                $locLabel = $ipData['label'];
            } else {
                $latVal = null;
                $lngVal = null;
                $locLabel = "Localisation Non Spécifiée";
            }

            // Dynamic strict Location Integration
            $diagnostic->setLatitude($latVal);
            $diagnostic->setLongitude($lngVal);
            $diagnostic->setLocationLabel($locLabel);

            $entityManager->persist($diagnostic);
            $entityManager->flush();

            // 6. Persist Traitement
            $traitement = new Traitement();
            $traitement->setDiagnostic($diagnostic);
            $traitement->setSolutionNom($traitementNom);
            $traitement->setTypeTraitement($typeTraitement);
            $traitement->setDescriptionDetaillee($description);

            $entityManager->persist($traitement);
            $entityManager->flush();

            // 7. Award Points and Check Badges
            $gamificationService->addPoints($user, 50); // 50 points for a diagnostic
            $gamificationService->checkDiagnosticBadges($user);

            // If result contains 'saine', check healthy badges
            if (str_contains(strtolower($diagnostic->getResultatIa()), 'saine')) {
                $gamificationService->checkHealthyBadges($user);
            }

            // Check if user has access to treatment details
            $hasTreatmentAccess = $featureService->getFeatures($user)['accesTraitement'];

            $traitementOutput = [
                'nom' => $hasTreatmentAccess ? $traitementNom : 'Verrouillé',
                'type' => $hasTreatmentAccess ? $typeTraitement : 'PREMIUM',
                'description' => $hasTreatmentAccess ? $description : 'Veuillez souscrire à un abonnement premium pour consulter le protocole de traitement complet, les directives de dosage et les instructions d\'application de l\'IA.'
            ];

            // Return Result JSON
            return $this->json([
                'success' => true,
                'diagnostic_id' => $diagnostic->getId(),
                'plante' => $plante,
                'maladie' => $maladie,
                'confiance' => $confiance,
                'severity' => $severity,
                'traitement' => $traitementOutput,
                'image_url' => $publicPath,
                // Add real-time limits and gamification
                'usage' => [
                    'used' => $featureService->getDiagnosticUsageCount($user),
                    'limit' => $featureService->getFeatures($user)['diagnosticsParHeure']
                ],
                'gamification' => [
                    'points' => $user->getPoints(),
                    'level' => $user->getLevel()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur inattendue : ' . $e->getMessage()], 500);
        }
    }
}

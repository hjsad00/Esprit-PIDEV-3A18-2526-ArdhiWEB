<?php

namespace App\Controller\UserAndDiag;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/health-scan')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HealthScanController extends AbstractController
{
    #[Route('/history', name: 'app_health_scan_history', methods: ['GET'])]
    public function history(\Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Fetch all scans for the current user, ordered by most recent
        $scans = $em->getRepository(\App\Entity\UserAndDiag\FarmHealthScan::class)->findBy(
            ['user' => $user],
            ['scanDate' => 'DESC']
        );

        // Fetch reports manually since the relation has been removed to match remote DB
        $reportsByScan = [];
        foreach ($scans as $scan) {
            $report = $em->getRepository(\App\Entity\UserAndDiag\FarmHealthReport::class)->findOneBy(['scan' => $scan], ['generatedAt' => 'DESC']);
            $reportsByScan[$scan->getId()] = $report;
        }

        return $this->render('UserAndDiag/health_scan/history.html.twig', [
            'scans' => $scans,
            'reportsByScan' => $reportsByScan
        ]);
    }

    #[Route('/new', name: 'app_health_scan_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        \Doctrine\ORM\EntityManagerInterface $em,
        \App\Service\UserAndDiag\GroqService $groqService,
        \App\Service\UserAndDiag\ImgBBService $imgBBService
    ): Response {
        if ($request->isMethod('POST')) {
            $user = $this->getUser();

            // 1. Create the base Scan entity
            $scan = new \App\Entity\UserAndDiag\FarmHealthScan();
            $scan->setUser($user);
            $scan->setCropType($request->request->get('cropType'));
            $scan->setPlantingDate(new \DateTime($request->request->get('plantingDate')));
            $scan->setGrowthStage($request->request->get('growthStage'));
            $scan->setConcerns($request->request->get('concerns'));
            $scan->setStatus('COMPLETED'); // Or 'PENDING' if you want to run this asynchronously later
            $scan->setScanDate(new \DateTime());

            // 2. Gather and upload the 5 images
            $imagePaths = [];
            $photoKeys = ['crops', 'soil', 'edges', 'insects', 'spacing'];
            foreach ($photoKeys as $key) {
                $file = $request->files->get("photo_$key");
                if ($file) {
                    $imageUrl = $imgBBService->uploadImage($file);
                    if ($imageUrl) {
                        $setter = 'setPhoto' . ucfirst($key);
                        if (method_exists($scan, $setter)) {
                            $scan->$setter($imageUrl);
                        }
                    }
                    $imagePaths[] = $file->getPathname(); // Get temp path for AI processing
                }
            }

            $em->persist($scan);
            $em->flush(); // S'assurer que le scan est enregistré avec les images même si l'IA échoue après

            // 3. Call the AI
            $scanDetails = [
                'crop' => $scan->getCropType(),
                'stage' => $scan->getGrowthStage(),
                'plantingDate' => $scan->getPlantingDate() ? $scan->getPlantingDate()->format('Y-m-d') : null,
                'concerns' => $scan->getConcerns()
            ];

            $aiResponseJson = $groqService->analyzeFarmHealth($imagePaths, $scanDetails);

            // Si la requête a échoué techniquement, GroqService renvoie un string commençant par ERREUR_
            if (str_starts_with($aiResponseJson, 'ERREUR_')) {
                die("<body style='background: #1e1e1e; color: #fff; padding: 50px; font-family: sans-serif;'><h2 style='color:#ff4757'>CRASH API GROQ</h2><p style='font-size:18px'>" . htmlspecialchars($aiResponseJson) . "</p></body>");
            }

            // Extract the JSON object vigorously in case the LLM included conversational filler
            $aiData = null;
            $startPos = strpos($aiResponseJson, '{');
            $endPos = strrpos($aiResponseJson, '}');
            if ($startPos !== false && $endPos !== false && $startPos < $endPos) {
                $jsonString = substr($aiResponseJson, $startPos, $endPos - $startPos + 1);
                $aiData = json_decode($jsonString, true);
            }

            if (!$aiData) {
                $this->addFlash('danger', 'L\'analyse IA a échoué. Cause : ' . substr($aiResponseJson, 0, 500));
                return $this->redirectToRoute('app_health_scan_new');
            }

            // 4. Create the Report Entity
            $report = new \App\Entity\UserAndDiag\FarmHealthReport();
            $report->setScan($scan);
            $report->setHealthScore($aiData['health_score'] ?? 0);
            $report->setBiodiversityScore($aiData['biodiversity_score'] ?? 0);
            $report->setLlavaAnalysis($aiData['llava_analysis'] ?? 'RAW JSON DUMP: ' . substr($aiResponseJson, 0, 800));
            $report->setGeneratedAt(new \DateTime());
            $em->persist($report);

            // 5. Create Vulnerabilities
            foreach ($aiData['vulnerabilities'] ?? [] as $vData) {
                $vuln = new \App\Entity\UserAndDiag\Vulnerability();
                $vuln->setReport($report);
                $vuln->setType($vData['type'] ?? 'DISEASE_RISK');
                $vuln->setThreat($vData['threat'] ?? 'Inconnu');
                $vuln->setSeverity($vData['severity'] ?? 'MEDIUM');
                $vuln->setDescription($vData['description'] ?? '');
                $vuln->setRiskScore($vData['risk_score'] ?? 0.0);
                $vuln->setTimeframeDays($vData['timeframe_days'] ?? 0);
                $vuln->setEstimatedYieldLossPercent($vData['yield_loss'] ?? 0.0);
                $vuln->setEstimatedCostIfOccurs($vData['cost'] ?? 0.0);
                $em->persist($vuln);
            }

            // 6. Create Prevention Plans
            foreach ($aiData['prevention_plans'] ?? [] as $pData) {
                $plan = new \App\Entity\UserAndDiag\PreventionPlan();
                $plan->setReport($report);
                $plan->setTitle($pData['title'] ?? 'Plan de prévention');
                $plan->setTimelineDays($pData['timeline_days'] ?? 0);
                $plan->setImpactLevel($pData['impact_level'] ?? 'MEDIUM');
                $totalT = $pData['total_tasks'] ?? 0;
                $plan->setSteps("Recommandation: $totalT tâches au total.");
                $em->persist($plan);
            }

            $em->flush();

            $this->addFlash('success', 'Le scan est terminé ! Voici votre rapport de santé.');
            return $this->redirectToRoute('app_health_scan_report', ['id' => $report->getId()]);
        }

        return $this->render('UserAndDiag/health_scan/form.html.twig');
    }
    #[Route('/{id}/report', name: 'app_health_scan_report', methods: ['GET'])]
    public function report(\App\Entity\UserAndDiag\FarmHealthReport $report, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        // Fetch manually since the relation has been removed
        $vulnerabilities = $em->getRepository(\App\Entity\UserAndDiag\Vulnerability::class)->findBy(['report' => $report]);
        $preventionPlans = $em->getRepository(\App\Entity\UserAndDiag\PreventionPlan::class)->findBy(['report' => $report]);

        return $this->render('UserAndDiag/health_scan/report.html.twig', [
            'report' => $report,
            'vulnerabilities' => $vulnerabilities,
            'preventionPlans' => $preventionPlans
        ]);
    }
}
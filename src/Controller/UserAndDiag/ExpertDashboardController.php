<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\Review;
use App\Entity\UserAndDiag\TreatmentPlan;
use App\Entity\UserAndDiag\TreatmentTask;
use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\PreventionTask;
use App\Repository\UserAndDiag\ReviewRepository;
use App\Service\UserAndDiag\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/expert')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ExpertDashboardController extends AbstractController
{
    #[Route('', name: 'app_expert_dashboard', methods: ['GET'])]
    public function index(ReviewRepository $reviewRepository): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($user->getRole() !== 'AGRONOME') {
            throw $this->createAccessDeniedException('Accès réservé aux agronomes.');
        }

        $reviews = $reviewRepository->findPendingReviews();

        return $this->render('UserAndDiag/expert_dashboard/index.html.twig', [
            'reviews' => $reviews,
        ]);
    }

    #[Route('/review/{id}', name: 'app_expert_review_detail', methods: ['GET'])]
    public function reviewDetail(Review $review, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'AGRONOME') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $diagnostic = $review->getDiagnostic();
        $farmer = $diagnostic ? $diagnostic->getUser() : null;

        $data = [
            'id' => $review->getId(),
            'review_type' => $review->getReviewType(),
            'status' => $review->getStatus(),
            'farmer_name' => $farmer ? ($farmer->getPrenom() . ' ' . $farmer->getNom()) : 'Inconnu',
            'diagnosis_result' => $diagnostic ? $diagnostic->getResultatIa() : null,
            'original_image_url' => $diagnostic ? $diagnostic->getImageScannee() : null,
            'photo_url' => $review->getPhotoUrl(),
            'ai_analysis' => $review->getAiAnalysis() ?? ($diagnostic ? $diagnostic->getResultatIa() : null),
            'created_at' => $review->getCreatedAt() ? $review->getCreatedAt()->format('d/m/Y H:i') : null,
            'tasks' => [],
            'treatment_plan_id' => null,
            'prevention_plan_id' => null,
        ];

        // Load treatment plan tasks
        if ($review->getTreatmentPlan()) {
            $data['treatment_plan_id'] = $review->getTreatmentPlan()->getId();
            $tasks = $em->getRepository(TreatmentTask::class)->findBy(
                ['treatmentPlan' => $review->getTreatmentPlan()],
                ['day_offset' => 'ASC']
            );
            foreach ($tasks as $task) {
                $data['tasks'][] = [
                    'id' => $task->getId(),
                    'day_offset' => $task->getDayOffset(),
                    'description' => $task->getTaskDescription(),
                    'status' => $task->getStatus(),
                    'type' => 'treatment',
                ];
            }
        }

        // Load prevention plan tasks
        if ($review->getPreventionPlan()) {
            $data['prevention_plan_id'] = $review->getPreventionPlan()->getId();
            $tasks = $em->getRepository(PreventionTask::class)->findBy(
                ['preventionPlan' => $review->getPreventionPlan()],
                ['day_offset' => 'ASC']
            );
            foreach ($tasks as $task) {
                $data['tasks'][] = [
                    'id' => $task->getId(),
                    'day_offset' => $task->getDayOffset(),
                    'description' => $task->getTaskDescription(),
                    'status' => $task->getStatus(),
                    'type' => 'prevention',
                ];
            }
        }

        return $this->json($data);
    }

    #[Route('/review/{id}/diagnosis', name: 'app_expert_submit_diagnosis', methods: ['POST'])]
    public function submitDiagnosisReview(Review $review, Request $request, EntityManagerInterface $em, NotificationService $notificationService): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'AGRONOME') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $diseaseName = trim($data['disease_name'] ?? '');
        $notes = trim($data['notes'] ?? '');

        if (empty($diseaseName)) {
            return $this->json(['error' => 'Veuillez indiquer le nom de la maladie.'], 400);
        }

        $review->setExpert($user);
        $review->setExpertDiseaseName($diseaseName);
        $review->setExpertNotes($notes);
        $review->setStatus('COMPLETED');
        $review->setUpdatedAt(new \DateTime());

        if ($review->getDiagnostic()) {
            $review->getDiagnostic()->setResultatIa($diseaseName);
        }

        $em->flush();

        if ($review->getDiagnostic() && $review->getDiagnostic()->getUser()) {
            $notificationService->notifyExpertReview($review->getDiagnostic()->getUser(), $review);
        }

        return $this->json(['success' => true, 'message' => 'Avis diagnostic soumis avec succès !']);
    }

    #[Route('/review/{id}/progress', name: 'app_expert_submit_progress', methods: ['POST'])]
    public function submitProgressReview(Review $review, Request $request, EntityManagerInterface $em, NotificationService $notificationService): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'AGRONOME') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $verdict = trim($data['verdict'] ?? '');
        $notes = trim($data['notes'] ?? '');

        $validVerdicts = ['CONTINUE', 'HEALED', 'WORSENED'];
        if (!in_array($verdict, $validVerdicts)) {
            return $this->json(['error' => 'Veuillez sélectionner un verdict valide.'], 400);
        }

        $review->setExpert($user);
        $review->setExpertVerdict($verdict);
        $review->setExpertNotes($notes);
        $review->setStatus('COMPLETED');
        $review->setUpdatedAt(new \DateTime());

        // If HEALED, complete the associated plan
        if ($verdict === 'HEALED') {
            if ($review->getTreatmentPlan()) {
                $review->getTreatmentPlan()->setStatus('COMPLETED');
            }
            if ($review->getPreventionPlan()) {
                $review->getPreventionPlan()->setStatus('COMPLETED');
            }
        }

        $em->flush();

        if ($review->getDiagnostic() && $review->getDiagnostic()->getUser()) {
            $notificationService->notifyExpertReview($review->getDiagnostic()->getUser(), $review);
        }

        $msg = $verdict === 'HEALED' ? 'Plan marqué comme résolu et terminé !' : 'Avis de suivi soumis !';
        return $this->json(['success' => true, 'message' => $msg]);
    }

    #[Route('/review/{id}/add-task', name: 'app_expert_add_task', methods: ['POST'])]
    public function addTask(Review $review, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'AGRONOME') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $day = (int) ($data['day'] ?? 0);
        $description = trim($data['description'] ?? '');

        if ($day <= 0 || empty($description)) {
            return $this->json(['error' => 'Veuillez remplir le jour et la description.'], 400);
        }

        if ($review->getTreatmentPlan()) {
            $task = new TreatmentTask();
            $task->setDayOffset($day);
            $task->setTaskDescription(substr($description, 0, 255));
            $task->setStatus('PENDING');
            $review->getTreatmentPlan()->addTask($task);
            $em->persist($task);
        } elseif ($review->getPreventionPlan()) {
            $task = new PreventionTask();
            $task->setDayOffset($day);
            $task->setTaskDescription(substr($description, 0, 255));
            $review->getPreventionPlan()->addTask($task);
            $em->persist($task);
        } else {
            return $this->json(['error' => 'Aucun plan associé à cette revue.'], 400);
        }

        $em->flush();

        return $this->json(['success' => true, 'message' => 'Nouvelle tâche ajoutée !', 'task_id' => $task->getId()]);
    }

    #[Route('/task/{id}/update', name: 'app_expert_update_task', methods: ['POST'])]
    public function updateTask(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'AGRONOME') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $newDesc = trim($data['description'] ?? '');
        $newDay = isset($data['day_offset']) ? (int) $data['day_offset'] : null;

        if (empty($newDesc)) {
            return $this->json(['error' => 'La description ne peut pas être vide.'], 400);
        }

        // Try treatment task first, then prevention task
        $task = $em->getRepository(TreatmentTask::class)->find($id);
        if ($task) {
            $task->setTaskDescription(substr($newDesc, 0, 255));
            if ($newDay !== null && $newDay > 0)
                $task->setDayOffset($newDay);
            $em->flush();
            return $this->json(['success' => true, 'message' => 'Tâche mise à jour !']);
        }

        $task = $em->getRepository(PreventionTask::class)->find($id);
        if ($task) {
            $task->setTaskDescription(substr($newDesc, 0, 255));
            if ($newDay !== null && $newDay > 0)
                $task->setDayOffset($newDay);
            $em->flush();
            return $this->json(['success' => true, 'message' => 'Tâche mise à jour !']);
        }

        return $this->json(['error' => 'Tâche introuvable.'], 404);
    }

    #[Route('/task/{id}/delete', name: 'app_expert_delete_task', methods: ['POST'])]
    public function deleteTask(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'AGRONOME') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $task = $em->getRepository(TreatmentTask::class)->find($id);
        if ($task) {
            $em->remove($task);
            $em->flush();
            return $this->json(['success' => true, 'message' => 'Tâche supprimée.']);
        }

        $task = $em->getRepository(PreventionTask::class)->find($id);
        if ($task) {
            $em->remove($task);
            $em->flush();
            return $this->json(['success' => true, 'message' => 'Tâche supprimée.']);
        }

        return $this->json(['error' => 'Tâche introuvable.'], 404);
    }
}

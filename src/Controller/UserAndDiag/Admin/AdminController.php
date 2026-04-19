<?php

namespace App\Controller\UserAndDiag\Admin;

use App\Repository\UserAndDiag\AbonnementRepository;
use App\Repository\UserAndDiag\BadgeRepository;
use App\Repository\UserAndDiag\CommunityCommentRepository;
use App\Repository\UserAndDiag\CommunityLikeRepository;
use App\Repository\UserAndDiag\CommunityPostRepository;
use App\Repository\UserAndDiag\DiagnosticRepository;
use App\Repository\UserAndDiag\FarmHealthReportRepository;
use App\Repository\UserAndDiag\FarmHealthScanRepository;
use App\Repository\UserAndDiag\OffreRepository;
use App\Repository\UserAndDiag\PreventionPlanRepository;
use App\Repository\UserAndDiag\PreventionTaskRepository;
use App\Repository\UserAndDiag\TraitementRepository;
use App\Repository\UserAndDiag\TreatmentPlanRepository;
use App\Repository\UserAndDiag\TreatmentTaskRepository;
use App\Repository\UserAndDiag\UserBadgeRepository;
use App\Repository\UserAndDiag\UserRepository;
use App\Repository\UserAndDiag\VulnerabilityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/user-diag')]
class AdminController extends AbstractController
{
    #[Route('/users-dashboard', name: 'admin_users_dashboard')]
    public function usersDashboard(
        UserRepository $userRepo,
        AbonnementRepository $abonnementRepo,
        BadgeRepository $badgeRepo,
        CommunityPostRepository $postRepo,
        CommunityCommentRepository $commentRepo,
        CommunityLikeRepository $likeRepo,
        OffreRepository $offreRepo,
        UserBadgeRepository $userBadgeRepo,
        \App\Repository\UserAndDiag\CommunityReportRepository $communityReportRepo,
        \App\Repository\UserAndDiag\UserBlockRepository $userBlockRepo,
        \App\Repository\UserAndDiag\ModerationAuditRepository $moderationAuditRepo
    ): Response {
        $stats = [
            ['label' => 'Utilisateurs', 'count' => $userRepo->count([]), 'icon' => 'bi-people-fill', 'color' => '#116530', 'route' => 'admin_user_index'],
            ['label' => 'Abonnements', 'count' => $abonnementRepo->count([]), 'icon' => 'bi-credit-card-fill', 'color' => '#6f42c1', 'route' => 'admin_abonnement_index'],
            ['label' => 'Badges', 'count' => $badgeRepo->count([]), 'icon' => 'bi-award-fill', 'color' => '#d63384', 'route' => 'admin_badge_index'],
            ['label' => 'Posts', 'count' => $postRepo->count([]), 'icon' => 'bi-chat-square-text-fill', 'color' => '#20c997', 'route' => 'admin_community_post_index'],
            ['label' => 'Commentaires', 'count' => $commentRepo->count([]), 'icon' => 'bi-chat-dots-fill', 'color' => '#0dcaf0', 'route' => 'admin_community_comment_index'],
            ['label' => 'Likes', 'count' => $likeRepo->count([]), 'icon' => 'bi-heart-fill', 'color' => '#dc3545', 'route' => 'admin_community_like_index'],
            ['label' => 'Offres', 'count' => $offreRepo->count([]), 'icon' => 'bi-tag-fill', 'color' => '#198754', 'route' => 'admin_offre_index'],
            ['label' => 'User Badges', 'count' => $userBadgeRepo->count([]), 'icon' => 'bi-patch-check-fill', 'color' => '#34495e', 'route' => 'admin_user_badge_index'],
            ['label' => 'Signalements', 'count' => $communityReportRepo->count([]), 'icon' => 'bi-flag-fill', 'color' => '#e67e22', 'route' => 'admin_community_report_index'],
            ['label' => 'Blocages', 'count' => $userBlockRepo->count([]), 'icon' => 'bi-slash-circle-fill', 'color' => '#c0392b', 'route' => 'admin_user_block_index'],
            ['label' => 'Audit Modé.', 'count' => $moderationAuditRepo->count([]), 'icon' => 'bi-shield-shaded', 'color' => '#8e44ad', 'route' => 'admin_moderation_audit_index'],
        ];

        return $this->render('UserAndDiag/admin/dashboard.html.twig', [
            'stats' => $stats,
            'title' => 'Gestion des Utilisateurs',
            'dashboard_context' => 'users',
        ]);
    }

    #[Route('/diags-dashboard', name: 'admin_diags_dashboard')]
    public function diagsDashboard(
        DiagnosticRepository $diagnosticRepo,
        TraitementRepository $traitementRepo,
        FarmHealthScanRepository $scanRepo,
        FarmHealthReportRepository $reportRepo,
        PreventionPlanRepository $preventionPlanRepo,
        PreventionTaskRepository $preventionTaskRepo,
        TreatmentPlanRepository $treatmentPlanRepo,
        TreatmentTaskRepository $treatmentTaskRepo,
        VulnerabilityRepository $vulnerabilityRepo,
        \App\Repository\UserAndDiag\ReviewRepository $reviewRepo,
        \App\Repository\UserAndDiag\DiagNotificationRepository $diagNotificationRepo
    ): Response {
        $stats = [
            ['label' => 'Diagnostics', 'count' => $diagnosticRepo->count([]), 'icon' => 'bi-search', 'color' => '#0d6efd', 'route' => 'admin_diagnostic_index'],
            ['label' => 'Traitements', 'count' => $traitementRepo->count([]), 'icon' => 'bi-capsule', 'color' => '#e74c3c', 'route' => 'admin_traitement_index'],
            ['label' => 'Scans Santé', 'count' => $scanRepo->count([]), 'icon' => 'bi-activity', 'color' => '#2ecc71', 'route' => 'admin_farm_health_scan_index'],
            ['label' => 'Rapports Santé', 'count' => $reportRepo->count([]), 'icon' => 'bi-file-earmark-medical-fill', 'color' => '#3498db', 'route' => 'admin_farm_health_report_index'],
            ['label' => 'Plans Prévention', 'count' => $preventionPlanRepo->count([]), 'icon' => 'bi-shield-fill-check', 'color' => '#f39c12', 'route' => 'admin_prevention_plan_index'],
            ['label' => 'Tâches Prévention', 'count' => $preventionTaskRepo->count([]), 'icon' => 'bi-list-check', 'color' => '#1abc9c', 'route' => 'admin_prevention_task_index'],
            ['label' => 'Plans Traitement', 'count' => $treatmentPlanRepo->count([]), 'icon' => 'bi-journal-medical', 'color' => '#9b59b6', 'route' => 'admin_treatment_plan_index'],
            ['label' => 'Tâches Traitement', 'count' => $treatmentTaskRepo->count([]), 'icon' => 'bi-check2-square', 'color' => '#e67e22', 'route' => 'admin_treatment_task_index'],
            ['label' => 'Vulnérabilités', 'count' => $vulnerabilityRepo->count([]), 'icon' => 'bi-bug-fill', 'color' => '#c0392b', 'route' => 'admin_vulnerability_index'],
            ['label' => 'Avis & Reviews', 'count' => $reviewRepo->count([]), 'icon' => 'bi-star-fill', 'color' => '#f1c40f', 'route' => 'admin_review_index'],
            ['label' => 'Notifications', 'count' => $diagNotificationRepo->count([]), 'icon' => 'bi-bell-fill', 'color' => '#34495e', 'route' => 'admin_diag_notification_index'],
        ];

        return $this->render('UserAndDiag/admin/dashboard.html.twig', [
            'stats' => $stats,
            'title' => 'Gestion des Diagnostics',
            'dashboard_context' => 'diags',
        ]);
    }
}

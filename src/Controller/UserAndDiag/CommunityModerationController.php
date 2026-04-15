<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\CommunityReport;
use App\Entity\UserAndDiag\ModerationAudit;
use App\Entity\UserAndDiag\User;
use App\Repository\UserAndDiag\CommunityReportRepository;
use App\Repository\UserAndDiag\ModerationAuditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/moderation')]
#[IsGranted('ROLE_MODERATOR')]
class CommunityModerationController extends AbstractController
{
    // ────────────────────────── DASHBOARD ──────────────────────────

    #[Route('', name: 'app_user_and_diag_moderation_dashboard', methods: ['GET'])]
    public function dashboard(
        CommunityReportRepository $reportRepo,
        ModerationAuditRepository $auditRepo,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Flagged posts sorted by report count
        $flaggedData = $reportRepo->findUnresolvedGroupedByPost();
        $flaggedPosts = [];
        foreach ($flaggedData as $row) {
            $post = $em->getRepository(CommunityPost::class)->find($row['post_id']);
            if ($post) {
                $flaggedPosts[] = [
                    'post' => $post,
                    'reportCount' => $row['report_count']
                ];
            }
        }

        // Recent audit log (last 50)
        $auditLog = $auditRepo->findBy([], ['created_at' => 'DESC'], 50);

        // All non-admin users for moderator assignment (admin-only section)
        $allUsers = [];
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $allUsers = $em->getRepository(User::class)->createQueryBuilder('u')
                ->where('u.role != :admin')
                ->setParameter('admin', 'ADMIN')
                ->orderBy('u.prenom', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('UserAndDiag/community/moderation_dashboard.html.twig', [
            'flaggedPosts' => $flaggedPosts,
            'auditLog' => $auditLog,
            'allUsers' => $allUsers,
            'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles()),
        ]);
    }

    // ────────────────────────── MUTE USER ──────────────────────────

    #[Route('/user/{id}/mute', name: 'app_user_and_diag_moderation_mute', methods: ['POST'])]
    public function muteUser(User $targetUser, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $mod */
        $mod = $this->getUser();

        // Cannot mute admins
        if ($targetUser->getRole() === 'ADMIN') {
            return $this->json(['error' => 'Impossible de rendre muet un administrateur.'], 403);
        }

        $duration = $request->request->get('duration', '1h'); // 1h, 1d, 7d
        $reason = trim($request->request->get('reason', 'Comportement inapproprié'));

        $mutedUntil = match ($duration) {
            '1h' => new \DateTime('+1 hour'),
            '1d' => new \DateTime('+1 day'),
            '7d' => new \DateTime('+7 days'),
            '30d' => new \DateTime('+30 days'),
            default => new \DateTime('+1 hour'),
        };

        $targetUser->setMutedUntil($mutedUntil);
        $this->logAudit($em, $mod, $targetUser, 'MUTE', $reason);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%s %s est muet jusqu\'au %s.', $targetUser->getPrenom(), $targetUser->getNom(), $mutedUntil->format('d/m/Y H:i'))
        ]);
    }

    // ────────────────────────── UNMUTE USER ──────────────────────────

    #[Route('/user/{id}/unmute', name: 'app_user_and_diag_moderation_unmute', methods: ['POST'])]
    public function unmuteUser(User $targetUser, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $mod */
        $mod = $this->getUser();

        $targetUser->setMutedUntil(null);
        $this->logAudit($em, $mod, $targetUser, 'UNMUTE', 'Levée manuelle du silence.');
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Utilisateur démuté avec succès.']);
    }

    // ────────────────────────── BAN USER ──────────────────────────

    #[Route('/user/{id}/ban', name: 'app_user_and_diag_moderation_ban', methods: ['POST'])]
    public function banUser(User $targetUser, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $mod */
        $mod = $this->getUser();

        if ($targetUser->getRole() === 'ADMIN') {
            return $this->json(['error' => 'Impossible de bannir un administrateur.'], 403);
        }

        $reason = trim($request->request->get('reason', 'Violation grave des règles.'));
        $targetUser->setIsBanned(true);
        $this->logAudit($em, $mod, $targetUser, 'BAN', $reason);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Utilisateur banni définitivement.']);
    }

    // ────────────────────────── UNBAN USER ──────────────────────────

    #[Route('/user/{id}/unban', name: 'app_user_and_diag_moderation_unban', methods: ['POST'])]
    public function unbanUser(User $targetUser, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $mod */
        $mod = $this->getUser();

        $targetUser->setIsBanned(false);
        $this->logAudit($em, $mod, $targetUser, 'UNBAN', 'Réhabilitation de l\'utilisateur.');
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Utilisateur débanni avec succès.']);
    }

    // ────────────────────────── DELETE POST ──────────────────────────

    #[Route('/post/{id}/delete', name: 'app_user_and_diag_moderation_delete_post', methods: ['POST'])]
    public function deletePost(CommunityPost $post, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $mod */
        $mod = $this->getUser();
        $reason = trim($request->request->get('reason', 'Contenu inapproprié.'));

        // Audit before deleting
        $this->logAudit($em, $mod, $post->getUser(), 'DELETE_POST', $reason, $post->getId());

        // Resolve all pending reports for this post
        $reports = $em->getRepository(CommunityReport::class)->findBy(['post' => $post, 'is_resolved' => false]);
        foreach ($reports as $report) {
            $report->setIsResolved(true);
        }

        // Delete related comments, likes, then the post
        $em->createQuery('DELETE FROM App\Entity\UserAndDiag\CommunityLike l WHERE l.post = :post')->setParameter('post', $post)->execute();
        $em->createQuery('DELETE FROM App\Entity\UserAndDiag\CommunityComment c WHERE c.post = :post')->setParameter('post', $post)->execute();
        $em->remove($post);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Publication supprimée.']);
    }

    // ────────────────────────── DELETE COMMENT ──────────────────────────

    #[Route('/comment/{id}/delete', name: 'app_user_and_diag_moderation_delete_comment', methods: ['POST'])]
    public function deleteComment(CommunityComment $comment, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $mod */
        $mod = $this->getUser();
        $reason = trim($request->request->get('reason', 'Commentaire inapproprié.'));

        $this->logAudit($em, $mod, $comment->getUser(), 'DELETE_COMMENT', $reason, null, $comment->getId());

        // Delete child comments first (replies)
        $children = $em->getRepository(CommunityComment::class)->findBy(['parentComment' => $comment]);
        foreach ($children as $child) {
            $em->remove($child);
        }

        // Delete likes on this comment
        $em->createQuery('DELETE FROM App\Entity\UserAndDiag\CommunityLike l WHERE l.comment = :comment')->setParameter('comment', $comment)->execute();
        $em->remove($comment);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Commentaire supprimé.']);
    }

    // ────────────────────────── GRANT/REVOKE MODERATOR ──────────────────────────

    #[Route('/user/{id}/toggle-mod', name: 'app_user_and_diag_moderation_toggle_mod', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleModerator(User $targetUser, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        if ($targetUser->getRole() === 'ADMIN') {
            return $this->json(['error' => 'Les administrateurs sont modérateurs par défaut.'], 400);
        }

        $newState = !$targetUser->isModerator();
        $targetUser->setIsModerator($newState);

        $action = $newState ? 'GRANT_MOD' : 'REVOKE_MOD';
        $this->logAudit($em, $admin, $targetUser, $action, $newState ? 'Promotion au rang de modérateur.' : 'Révocation des droits de modération.');
        $em->flush();

        return $this->json([
            'success' => true,
            'is_moderator' => $newState,
            'message' => $newState ? 'Droits de modération accordés.' : 'Droits de modération révoqués.'
        ]);
    }

    // ────────────────────────── RESOLVE REPORTS ──────────────────────────

    #[Route('/post/{id}/resolve-reports', name: 'app_user_and_diag_moderation_resolve_reports', methods: ['POST'])]
    public function resolveReports(CommunityPost $post, EntityManagerInterface $em): JsonResponse
    {
        $reports = $em->getRepository(CommunityReport::class)->findBy(['post' => $post, 'is_resolved' => false]);
        foreach ($reports as $report) {
            $report->setIsResolved(true);
        }
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Signalements résolus.']);
    }

    // ────────────────────────── HELPER ──────────────────────────

    private function logAudit(
        EntityManagerInterface $em,
        User $moderator,
        User $target,
        string $action,
        ?string $reason = null,
        ?int $postId = null,
        ?int $commentId = null
    ): void {
        $audit = new ModerationAudit();
        $audit->setModerator($moderator);
        $audit->setTargetUser($target);
        $audit->setAction($action);
        $audit->setReason($reason);
        $audit->setRelatedPostId($postId);
        $audit->setRelatedCommentId($commentId);
        $em->persist($audit);
    }
}

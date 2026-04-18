<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\CommunityLike;
use App\Entity\UserAndDiag\CommunityPost;
use App\Repository\UserAndDiag\CommunityCommentRepository;
use App\Repository\UserAndDiag\CommunityLikeRepository;
use App\Repository\UserAndDiag\CommunityPostRepository;
use App\Service\UserAndDiag\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/community')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CommunityController extends AbstractController
{
    // ────────────────────────── MENTIONS API ──────────────────────────

    #[Route('/api/users/search', name: 'app_user_and_diag_community_users_search', methods: ['GET'])]
    public function searchUsers(Request $request, \App\Repository\UserAndDiag\UserRepository $userRepo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));

        if (strlen($q) < 1) {
            $users = $userRepo->createQueryBuilder('u')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        } else {
            $users = $userRepo->createQueryBuilder('u')
                ->where('u.prenom LIKE :q OR u.nom LIKE :q')
                ->setParameter('q', $q . '%')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        $data = [];
        foreach ($users as $u) {
            $data[] = [
                'prenom' => $u->getPrenom(),
                'nom' => $u->getNom(),
                'avatar' => $u->getAvatar()
            ];
        }

        return $this->json($data);
    }

    // ────────────────────────── FEED ──────────────────────────

    #[Route('', name: 'app_user_and_diag_community', methods: ['GET'])]
    public function feed(
        Request $request,
        CommunityPostRepository $postRepo,
        CommunityCommentRepository $commentRepo,
        CommunityLikeRepository $likeRepo,
        \App\Repository\UserAndDiag\ModerationAuditRepository $auditRepo
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $keyword = $request->query->get('q', '');

        $posts = $keyword
            ? $postRepo->searchByKeyword($keyword)
            : $postRepo->findAllOrderedByDate();

        $feedData = [];
        foreach ($posts as $post) {
            $commentCount = $commentRepo->countByPost($post);
            $vote = $likeRepo->findPostVote($user, $post);

            $feedData[] = [
                'post' => $post,
                'commentCount' => $commentCount,
                'userVote' => $vote ? $vote->getVoteType() : null,
            ];
        }

        // Trending algorithm sort
        usort($feedData, function ($a, $b) {
            $pA = $a['post'];
            $pB = $b['post'];

            // Score = (Likes * 10) + (CTR percentage) + (Dwell time in minutes)
            $ctrA = $pA->getFeedImpressions() > 0 ? ($pA->getViews() / $pA->getFeedImpressions() * 100) : 0;
            $scoreA = ($pA->getLikes() * 10) + $ctrA + ($pA->getTotalFeedDwellTime() / 60);

            $ctrB = $pB->getFeedImpressions() > 0 ? ($pB->getViews() / $pB->getFeedImpressions() * 100) : 0;
            $scoreB = ($pB->getLikes() * 10) + $ctrB + ($pB->getTotalFeedDwellTime() / 60);

            // Also heavily weight recency so old posts decay
            // Decay: -1 point per hour old
            $hoursA = (time() - $pA->getCreatedAt()->getTimestamp()) / 3600;
            $hoursB = (time() - $pB->getCreatedAt()->getTimestamp()) / 3600;

            $scoreA -= $hoursA;
            $scoreB -= $hoursB;

            return $scoreB <=> $scoreA;
        });

        $latestMuteReason = null;
        if ($user && $user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            $latestMuteReason = $auditRepo->findLatestMuteReasonForUser($user);
        }

        return $this->render('UserAndDiag/community/feed.html.twig', [
            'feedData' => $feedData,
            'keyword' => $keyword,
            'isModerator' => $this->isGranted('ROLE_MODERATOR'),
            'latestMuteReason' => $latestMuteReason,
        ]);
    }

    // ────────────────────── CREATE POST ───────────────────────

    #[Route('/new', name: 'app_user_and_diag_community_new', methods: ['GET', 'POST'])]
    public function createPost(Request $request, EntityManagerInterface $em, \App\Service\UserAndDiag\GamificationService $gamificationService, \App\Service\UserAndDiag\ImgBBService $imgBBService, \App\Repository\UserAndDiag\ModerationAuditRepository $auditRepo, NotificationService $notificationService, \App\Repository\UserAndDiag\UserRepository $userRepo): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // ── Mute/Ban Gate ──
        if ($user->isBanned()) {
            $this->addFlash('danger', '⛔ Votre compte a été banni de la communauté.');
            return $this->redirectToRoute('app_user_and_diag_community');
        }
        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            $latestMute = $auditRepo->findLatestMuteReasonForUser($user);
            $reason = $latestMute ? ' Raison : ' . $latestMute : '';
            $this->addFlash('danger', '🔇 Vous êtes muet jusqu\'au ' . $user->getMutedUntil()->format('d/m/Y H:i') . '.' . $reason);
            return $this->redirectToRoute('app_user_and_diag_community');
        }

        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));

            $post = new CommunityPost();
            $post->setUser($user);
            $post->setTitle($title);
            $post->setDescription($description);

            // Handle image upload via ImgBB
            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $imgUrl = $imgBBService->uploadImage($imageFile);
                if ($imgUrl) {
                    $post->setImageUrl($imgUrl);
                }
            }

            $em->persist($post);
            $em->flush();

            // Detect mentions: @Prenom
            preg_match_all('/@([a-zA-ZÀ-ÿ-]+)/', $description, $matches);
            if (!empty($matches[1])) {
                foreach (array_unique($matches[1]) as $prenomMention) {
                    $mentionedUser = $userRepo->findOneBy(['prenom' => $prenomMention]);
                    if ($mentionedUser && $mentionedUser->getId() !== $user->getId()) {
                        $notificationService->notifyMention($mentionedUser, $user->getPrenom(), $post->getId());
                    }
                }
            }

            // Award points for asking a question
            $gamificationService->addPoints($user, 20);
            $gamificationService->checkPointBadges($user);

            $this->addFlash('success', 'Question publiée avec succès !');
            return $this->redirectToRoute('app_user_and_diag_community');
        }

        return $this->render('UserAndDiag/community/create.html.twig');
    }

    // ────────────────────── EDIT / DELETE POST ──────────────────────

    #[Route('/{id}/edit', name: 'app_user_and_diag_community_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editPost(
        CommunityPost $post,
        Request $request,
        EntityManagerInterface $em,
        \App\Service\UserAndDiag\ImgBBService $imgBBService
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($post->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette publication.');
        }

        if ($user->isBanned()) {
            $this->addFlash('danger', '⛔ Action bloquée (compte banni).');
            return $this->redirectToRoute('app_user_and_diag_community_detail', ['id' => $post->getId()]);
        }

        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            $this->addFlash('danger', '🔇 Action bloquée (compte muet).');
            return $this->redirectToRoute('app_user_and_diag_community_detail', ['id' => $post->getId()]);
        }

        if ($request->isMethod('POST')) {
            $post->setTitle(trim($request->request->get('title', $post->getTitle())));
            $post->setDescription(trim($request->request->get('description', $post->getDescription())));

            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $imgUrl = $imgBBService->uploadImage($imageFile);
                if ($imgUrl) {
                    $post->setImageUrl($imgUrl);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Publication modifiée avec succès.');
            return $this->redirectToRoute('app_user_and_diag_community_detail', ['id' => $post->getId()]);
        }

        return $this->render('UserAndDiag/community/edit.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_and_diag_community_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deletePost(
        CommunityPost $post,
        EntityManagerInterface $em
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($post->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_MODERATOR')) {
            throw $this->createAccessDeniedException('Non autorisé.');
        }

        if ($user->isBanned()) {
            $this->addFlash('danger', '⛔ Action bloquée (compte banni).');
            return $this->redirectToRoute('app_user_and_diag_community');
        }

        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            $this->addFlash('danger', '🔇 Action bloquée (compte muet).');
            return $this->redirectToRoute('app_user_and_diag_community');
        }

        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Publication supprimée.');
        return $this->redirectToRoute('app_user_and_diag_community');
    }

    // ────────────────────── POST DETAIL ───────────────────────

    #[Route('/{id}', name: 'app_user_and_diag_community_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(
        CommunityPost $post,
        CommunityCommentRepository $commentRepo,
        CommunityLikeRepository $likeRepo,
        EntityManagerInterface $em
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $comments = $commentRepo->findByPost($post);
        $postVote = $likeRepo->findPostVote($user, $post);

        // Build comment vote map
        $commentVotes = [];
        foreach ($comments as $c) {
            $cv = $likeRepo->findCommentVote($user, $c);
            $commentVotes[$c->getId()] = $cv ? $cv->getVoteType() : null;
        }

        // Organize into tree
        $rootComments = [];
        $childrenMap = [];
        foreach ($comments as $c) {
            if ($c->getParentComment() === null) {
                $rootComments[] = $c;
            } else {
                $parentId = $c->getParentComment()->getId();
                $childrenMap[$parentId][] = $c;
            }
        }

        $chartLabels = [];
        $chartViews = [];
        if ($user && $post->getUser()->getId() === $user->getId()) {
            $logs = $em->getRepository(\App\Entity\UserAndDiag\CommunityAnalyticsDaily::class)
                ->createQueryBuilder('a')
                ->where('a.post = :post')
                ->setParameter('post', $post)
                ->orderBy('a.date', 'ASC')
                ->setMaxResults(14)
                ->getQuery()
                ->getResult();

            foreach ($logs as $log) {
                $chartLabels[] = $log->getDate()->format('d/m');
                $chartViews[] = $log->getViews();
            }
        }

        return $this->render('UserAndDiag/community/detail.html.twig', [
            'post' => $post,
            'postVote' => $postVote ? $postVote->getVoteType() : null,
            'rootComments' => $rootComments,
            'childrenMap' => $childrenMap,
            'commentVotes' => $commentVotes,
            'commentCount' => count($comments),
            'chartLabels' => $chartLabels,
            'chartViews' => $chartViews,
        ]);
    }

    // ────────────────────────── AJAX APIs ──────────────────────────

    #[Route('/{id}/vote', name: 'app_user_and_diag_community_vote_post', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function votePost(CommunityPost $post, Request $request, EntityManagerInterface $em, CommunityLikeRepository $likeRepo, \App\Repository\UserAndDiag\ModerationAuditRepository $auditRepo, NotificationService $notificationService): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($user->isBanned()) {
            return $this->json(['error' => '⛔ Action bloquée (compte banni).'], 403);
        }

        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            $latestMute = $auditRepo->findLatestMuteReasonForUser($user);
            $reason = $latestMute ? ' Raison : ' . $latestMute : '';
            return $this->json(['error' => '🔇 Vous êtes muet jusqu\'au ' . $user->getMutedUntil()->format('d/m/Y H:i') . '.' . $reason], 403);
        }

        $voteType = $request->request->get('vote'); // LIKE or DISLIKE

        if ($post->getUser()->isBlocking($user)) {
            return $this->json(['error' => '❌ Vous avez été bloqué par l\'auteur de cette publication.'], 403);
        }

        $existing = $likeRepo->findPostVote($user, $post);

        if ($existing) {
            if ($existing->getVoteType() === $voteType) {
                // Toggle off
                $this->updatePostCounts($post, $existing->getVoteType(), -1);
                $em->remove($existing);
                $em->flush();
                return $this->json(['likes' => $post->getLikes(), 'dislikes' => $post->getDislikes(), 'userVote' => null]);
            } else {
                // Switch vote
                $this->updatePostCounts($post, $existing->getVoteType(), -1);
                $existing->setVoteType($voteType);
                $this->updatePostCounts($post, $voteType, 1);
                $em->flush();

                if ($voteType === 'LIKE' && $post->getUser()->getId() !== $user->getId()) {
                    $notificationService->notifyPostLike($post->getUser(), $user->getPrenom(), $post->getId());
                } elseif ($voteType === 'DISLIKE' && $post->getUser()->getId() !== $user->getId()) {
                    $notificationService->notifyPostDislike($post->getUser(), $user->getPrenom(), $post->getId());
                }

                return $this->json(['likes' => $post->getLikes(), 'dislikes' => $post->getDislikes(), 'userVote' => $voteType]);
            }
        } else {
            $like = new CommunityLike();
            $like->setUser($user);
            $like->setPost($post);
            $like->setVoteType($voteType);
            $this->updatePostCounts($post, $voteType, 1);
            $em->persist($like);
            $em->flush();

            if ($voteType === 'LIKE' && $post->getUser()->getId() !== $user->getId()) {
                $notificationService->notifyPostLike($post->getUser(), $user->getPrenom(), $post->getId());
            } elseif ($voteType === 'DISLIKE' && $post->getUser()->getId() !== $user->getId()) {
                $notificationService->notifyPostDislike($post->getUser(), $user->getPrenom(), $post->getId());
            }

            return $this->json(['likes' => $post->getLikes(), 'dislikes' => $post->getDislikes(), 'userVote' => $voteType]);
        }
    }

    #[Route('/comment/{id}/vote', name: 'app_user_and_diag_community_vote_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function voteComment(CommunityComment $comment, Request $request, EntityManagerInterface $em, CommunityLikeRepository $likeRepo, \App\Repository\UserAndDiag\ModerationAuditRepository $auditRepo, NotificationService $notificationService): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($user->isBanned()) {
            return $this->json(['error' => '⛔ Action bloquée (compte banni).'], 403);
        }

        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            $latestMute = $auditRepo->findLatestMuteReasonForUser($user);
            $reason = $latestMute ? ' Raison : ' . $latestMute : '';
            return $this->json(['error' => '🔇 Vous êtes muet jusqu\'au ' . $user->getMutedUntil()->format('d/m/Y H:i') . '.' . $reason], 403);
        }

        $voteType = $request->request->get('vote');

        if ($comment->getUser()->isBlocking($user)) {
            return $this->json(['error' => '❌ Vous avez été bloqué par l\'auteur de ce commentaire.'], 403);
        }

        $existing = $likeRepo->findCommentVote($user, $comment);

        if ($existing) {
            if ($existing->getVoteType() === $voteType) {
                $this->updateCommentCounts($comment, $existing->getVoteType(), -1);
                $em->remove($existing);
                $em->flush();
                return $this->json(['likes' => $comment->getLikes(), 'dislikes' => $comment->getDislikes(), 'userVote' => null]);
            } else {
                $this->updateCommentCounts($comment, $existing->getVoteType(), -1);
                $existing->setVoteType($voteType);
                $this->updateCommentCounts($comment, $voteType, 1);
                $em->flush();

                if ($voteType === 'LIKE' && $comment->getUser()->getId() !== $user->getId()) {
                    $notificationService->notifyCommentLike($comment->getUser(), $user->getPrenom(), $comment->getId());
                } elseif ($voteType === 'DISLIKE' && $comment->getUser()->getId() !== $user->getId()) {
                    $notificationService->notifyCommentDislike($comment->getUser(), $user->getPrenom(), $comment->getId());
                }

                return $this->json(['likes' => $comment->getLikes(), 'dislikes' => $comment->getDislikes(), 'userVote' => $voteType]);
            }
        } else {
            $like = new CommunityLike();
            $like->setUser($user);
            $like->setComment($comment);
            $like->setVoteType($voteType);
            $this->updateCommentCounts($comment, $voteType, 1);
            $em->persist($like);
            $em->flush();

            if ($voteType === 'LIKE' && $comment->getUser()->getId() !== $user->getId()) {
                $notificationService->notifyCommentLike($comment->getUser(), $user->getPrenom(), $comment->getId());
            } elseif ($voteType === 'DISLIKE' && $comment->getUser()->getId() !== $user->getId()) {
                $notificationService->notifyCommentDislike($comment->getUser(), $user->getPrenom(), $comment->getId());
            }

            return $this->json(['likes' => $comment->getLikes(), 'dislikes' => $comment->getDislikes(), 'userVote' => $voteType]);
        }
    }

    #[Route('/{id}/comment', name: 'app_user_and_diag_community_add_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addComment(CommunityPost $post, Request $request, EntityManagerInterface $em, \App\Service\UserAndDiag\GamificationService $gamificationService, \App\Repository\UserAndDiag\ModerationAuditRepository $auditRepo, NotificationService $notificationService, \App\Repository\UserAndDiag\UserRepository $userRepo): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // ── Mute/Ban Gate ──
        if ($user->isBanned()) {
            return $this->json(['error' => '⛔ Votre compte a été banni de la communauté.'], 403);
        }
        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            $latestMute = $auditRepo->findLatestMuteReasonForUser($user);
            $reason = $latestMute ? ' Raison : ' . $latestMute : '';
            return $this->json(['error' => '🔇 Vous êtes muet jusqu\'au ' . $user->getMutedUntil()->format('d/m/Y H:i') . '.' . $reason], 403);
        }

        if ($post->getUser()->isBlocking($user)) {
            return $this->json(['error' => '❌ Vous avez été bloqué par l\'auteur de cette publication.'], 403);
        }

        $content = trim($request->request->get('content', ''));
        $parentId = $request->request->get('parent_id');

        if (!$content) {
            return $this->json(['error' => 'Contenu vide'], 400);
        }

        $comment = new CommunityComment();
        $comment->setPost($post);
        $comment->setUser($user);
        $comment->setContent($content);

        if ($parentId) {
            $parent = $em->getRepository(CommunityComment::class)->find($parentId);
            if ($parent) {
                if ($parent->getUser()->isBlocking($user)) {
                    return $this->json(['error' => '❌ Vous avez été bloqué par l\'auteur de ce commentaire.'], 403);
                }
                $comment->setParentComment($parent);
            }
        }

        $em->persist($comment);
        $em->flush();

        if ($parentId && isset($parent) && $parent->getUser()->getId() !== $user->getId()) {
            $notificationService->notifyCommentReply($parent->getUser(), $user->getPrenom(), $comment->getId());
        } elseif (!$parentId && $post->getUser()->getId() !== $user->getId()) {
            $notificationService->notifyPostComment($post->getUser(), $user->getPrenom(), $comment->getId());
        }
        $em->flush();

        // ── Detect Mentions ──
        preg_match_all('/@([a-zA-ZÀ-ÿ-]+)/', $content, $matches);
        if (!empty($matches[1])) {
            foreach (array_unique($matches[1]) as $prenomMention) {
                $mentionedUser = $userRepo->findOneBy(['prenom' => $prenomMention]);
                if ($mentionedUser && $mentionedUser->getId() !== $user->getId()) {
                    $notificationService->notifyMention($mentionedUser, $user->getPrenom(), $comment->getId(), 'COMMENT');
                }
            }
        }

        // Award points for participating (commenting)
        $gamificationService->addPoints($user, 10);
        $gamificationService->checkPointBadges($user);
        $gamificationService->checkSolutionBadges($user); // Counts total comments in the current logic

        return $this->json([
            'id' => $comment->getId(),
            'userName' => $user->getPrenom() . ' ' . $user->getNom(),
            'content' => $comment->getContent(),
            'date' => $comment->getCreatedAt()->format('d/m/Y H:i'),
        ]);
    }

    #[Route('/comment/{id}/edit', name: 'app_user_and_diag_community_edit_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function editComment(CommunityComment $comment, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($comment->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        if ($user->isBanned()) {
            return $this->json(['error' => 'Action bloquée (compte banni).'], 403);
        }

        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            return $this->json(['error' => 'Action bloquée (compte muet).'], 403);
        }

        $content = trim($request->request->get('content', ''));
        if (!$content) {
            return $this->json(['error' => 'Le contenu ne peut pas être vide.'], 400);
        }

        $comment->setContent($content);
        $em->flush();

        return $this->json(['success' => true, 'content' => $comment->getContent()]);
    }

    #[Route('/comment/{id}/delete', name: 'app_user_and_diag_community_delete_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteComment(CommunityComment $comment, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($comment->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_MODERATOR')) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        if ($user->isBanned()) {
            return $this->json(['error' => 'Action bloquée (compte banni).'], 403);
        }

        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            return $this->json(['error' => 'Action bloquée (compte muet).'], 403);
        }

        $em->remove($comment);
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/solve/{commentId}', name: 'app_user_and_diag_community_mark_solution', methods: ['POST'], requirements: ['id' => '\d+', 'commentId' => '\d+'])]
    public function markSolution(CommunityPost $post, int $commentId, EntityManagerInterface $em, \App\Service\UserAndDiag\GamificationService $gamificationService): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($post->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $comment = $em->getRepository(CommunityComment::class)->find($commentId);
        if (!$comment) {
            return $this->json(['error' => 'Commentaire introuvable'], 404);
        }

        $comment->setIsSolution(true);
        $post->setIsResolved(true);
        $post->setSolutionComment($comment);

        // Award points and badges to the user who wrote the accepted solution
        $commentAuthor = $comment->getUser();
        if ($commentAuthor) {
            $gamificationService->addPoints($commentAuthor, 100); // 100 points for a helpful solution
            $gamificationService->checkSolutionBadges($commentAuthor);
        }

        $em->flush();

        return $this->json(['success' => true]);
    }

    // ────────────────────── Helpers ───────────────────────

    private function updatePostCounts(CommunityPost $post, string $type, int $delta): void
    {
        if ($type === 'LIKE') {
            $post->setLikes(max(0, $post->getLikes() + $delta));
        } else {
            $post->setDislikes(max(0, $post->getDislikes() + $delta));
        }
    }

    private function updateCommentCounts(CommunityComment $comment, string $type, int $delta): void
    {
        if ($type === 'LIKE') {
            $comment->setLikes(max(0, $comment->getLikes() + $delta));
        } else {
            $comment->setDislikes(max(0, $comment->getDislikes() + $delta));
        }
    }

    // ────────────────────── REPORT POST ───────────────────────

    #[Route('/{id}/report', name: 'app_user_and_diag_community_report', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reportPost(
        CommunityPost $post,
        Request $request,
        EntityManagerInterface $em,
        \App\Repository\UserAndDiag\CommunityReportRepository $reportRepo
    ): JsonResponse {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($user->isBanned()) {
            return $this->json(['error' => '⛔ Action bloquée (compte banni).'], 403);
        }
        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            return $this->json(['error' => '🔇 Action bloquée (compte muet).'], 403);
        }

        // Can't report your own post
        if ($post->getUser()->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas signaler votre propre publication.'], 400);
        }

        // Duplicate check
        if ($reportRepo->hasUserReportedPost($user, $post)) {
            return $this->json(['error' => 'Vous avez déjà signalé cette publication.'], 400);
        }

        $reason = trim($request->request->get('reason', 'Contenu inapproprié'));

        $report = new \App\Entity\UserAndDiag\CommunityReport();
        $report->setReporter($user);
        $report->setPost($post);
        $report->setReason($reason);

        $em->persist($report);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Publication signalée. Nos modérateurs vont examiner le contenu.']);
    }

    // ────────────────────── REPORT COMMENT ───────────────────────

    #[Route('/comment/{id}/report', name: 'app_user_and_diag_community_comment_report', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reportComment(
        CommunityComment $comment,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($user->isBanned()) {
            return $this->json(['error' => '⛔ Action bloquée (compte banni).'], 403);
        }
        if ($user->getMutedUntil() && $user->getMutedUntil() > new \DateTime()) {
            return $this->json(['error' => '🔇 Action bloquée (compte muet).'], 403);
        }

        if ($comment->getUser()->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas signaler votre propre commentaire.'], 400);
        }

        // Check if report already exists for this comment by this user
        $existing = $em->getRepository(\App\Entity\UserAndDiag\CommunityReport::class)->findOneBy([
            'reporter' => $user,
            'comment' => $comment
        ]);

        if ($existing) {
            return $this->json(['error' => 'Vous avez déjà signalé ce commentaire.'], 400);
        }

        $reason = trim($request->request->get('reason', 'Commentaire inapproprié'));

        $report = new \App\Entity\UserAndDiag\CommunityReport();
        $report->setReporter($user);
        $report->setComment($comment);
        $report->setReason($reason);

        $em->persist($report);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Commentaire signalé avec succès.']);
    }
}

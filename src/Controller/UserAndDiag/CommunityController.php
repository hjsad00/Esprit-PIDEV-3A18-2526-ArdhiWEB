<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\CommunityLike;
use App\Entity\UserAndDiag\CommunityPost;
use App\Repository\UserAndDiag\CommunityCommentRepository;
use App\Repository\UserAndDiag\CommunityLikeRepository;
use App\Repository\UserAndDiag\CommunityPostRepository;
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
    // ────────────────────────── FEED ──────────────────────────

    #[Route('', name: 'app_user_and_diag_community', methods: ['GET'])]
    public function feed(
        Request $request,
        CommunityPostRepository $postRepo,
        CommunityCommentRepository $commentRepo,
        CommunityLikeRepository $likeRepo
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

        return $this->render('UserAndDiag/community/feed.html.twig', [
            'feedData' => $feedData,
            'keyword' => $keyword,
        ]);
    }

    // ────────────────────── CREATE POST ───────────────────────

    #[Route('/new', name: 'app_user_and_diag_community_new', methods: ['GET', 'POST'])]
    public function createPost(Request $request, EntityManagerInterface $em, \App\Service\UserAndDiag\GamificationService $gamificationService, \App\Service\UserAndDiag\ImgBBService $imgBBService): Response
    {
        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));

            if (!$title || !$description) {
                $this->addFlash('danger', 'Le titre et la description sont obligatoires.');
                return $this->redirectToRoute('app_user_and_diag_community_new');
            }

            /** @var \App\Entity\UserAndDiag\User $user */
            $user = $this->getUser();

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

            // Award points for asking a question
            $gamificationService->addPoints($user, 20);
            $gamificationService->checkPointBadges($user);

            $this->addFlash('success', 'Question publiée avec succès !');
            return $this->redirectToRoute('app_user_and_diag_community');
        }

        return $this->render('UserAndDiag/community/create.html.twig');
    }

    // ────────────────────── POST DETAIL ───────────────────────

    #[Route('/{id}', name: 'app_user_and_diag_community_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(
        CommunityPost $post,
        CommunityCommentRepository $commentRepo,
        CommunityLikeRepository $likeRepo
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

        return $this->render('UserAndDiag/community/detail.html.twig', [
            'post' => $post,
            'postVote' => $postVote ? $postVote->getVoteType() : null,
            'rootComments' => $rootComments,
            'childrenMap' => $childrenMap,
            'commentVotes' => $commentVotes,
            'commentCount' => count($comments),
        ]);
    }

    // ────────────────────────── AJAX APIs ──────────────────────────

    #[Route('/{id}/vote', name: 'app_user_and_diag_community_vote_post', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function votePost(CommunityPost $post, Request $request, EntityManagerInterface $em, CommunityLikeRepository $likeRepo): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $voteType = $request->request->get('vote'); // LIKE or DISLIKE

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
            return $this->json(['likes' => $post->getLikes(), 'dislikes' => $post->getDislikes(), 'userVote' => $voteType]);
        }
    }

    #[Route('/comment/{id}/vote', name: 'app_user_and_diag_community_vote_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function voteComment(CommunityComment $comment, Request $request, EntityManagerInterface $em, CommunityLikeRepository $likeRepo): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $voteType = $request->request->get('vote');

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
            return $this->json(['likes' => $comment->getLikes(), 'dislikes' => $comment->getDislikes(), 'userVote' => $voteType]);
        }
    }

    #[Route('/{id}/comment', name: 'app_user_and_diag_community_add_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addComment(CommunityPost $post, Request $request, EntityManagerInterface $em, \App\Service\UserAndDiag\GamificationService $gamificationService): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
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
                $comment->setParentComment($parent);
            }
        }

        $em->persist($comment);
        $em->flush();

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
}

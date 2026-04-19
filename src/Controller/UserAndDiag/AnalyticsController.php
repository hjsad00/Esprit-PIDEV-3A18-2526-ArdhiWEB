<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\CommunityAnalyticsDaily;
use App\Repository\UserAndDiag\CommunityPostRepository;
use App\Repository\UserAndDiag\CommunityCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user-and-diag/community/analytics')]
class AnalyticsController extends AbstractController
{
    #[Route('/beacon', name: 'app_user_and_diag_community_analytics_beacon', methods: ['POST'])]
    public function receiveBeacon(
        Request $request,
        EntityManagerInterface $em,
        CommunityPostRepository $postRepo,
        CommunityCommentRepository $commentRepo
    ): JsonResponse {
        // Parse the incoming JSON batch
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $postsMetrics = $data['posts'] ?? [];
        $commentsMetrics = $data['comments'] ?? [];

        $today = new \DateTime('today');

        // Process POST analytics
        foreach ($postsMetrics as $postId => $metrics) {
            $post = $postRepo->find($postId);
            if ($post) {
                if (isset($metrics['dwellTime'])) {
                    $post->setTotalFeedDwellTime(min(999999999, ($post->getTotalFeedDwellTime() ?: 0) + $metrics['dwellTime']));
                }
                if (isset($metrics['impressions'])) {
                    $post->setFeedImpressions(($post->getFeedImpressions() ?: 0) + $metrics['impressions']);
                }
                if (isset($metrics['readTime'])) {
                    $post->setTotalReadTime(min(999999999, ($post->getTotalReadTime() ?: 0) + $metrics['readTime']));
                }
                if (isset($metrics['views'])) {
                    $post->setViews(($post->getViews() ?: 0) + $metrics['views']);
                }
                if (isset($metrics['completedReads'])) {
                    $post->setCompletedReads(($post->getCompletedReads() ?: 0) + $metrics['completedReads']);
                }
                if (isset($metrics['mediaClicks'])) {
                    $post->setMediaClicks(($post->getMediaClicks() ?: 0) + $metrics['mediaClicks']);
                }

                if (isset($metrics['views']) || isset($metrics['readTime'])) {
                    // Update exact daily log
                    $dailyLog = $em->getRepository(CommunityAnalyticsDaily::class)->findOneBy([
                        'post' => $post,
                        'date' => $today
                    ]);
                    if (!$dailyLog) {
                        $dailyLog = new CommunityAnalyticsDaily();
                        $dailyLog->setPost($post);
                        $dailyLog->setDate($today);
                        $em->persist($dailyLog);
                    }
                    if (isset($metrics['views'])) {
                        $dailyLog->setViews($dailyLog->getViews() + $metrics['views']);
                    }
                    if (isset($metrics['readTime'])) {
                        $dailyLog->setReadTime($dailyLog->getReadTime() + $metrics['readTime']);
                    }
                }
            }
        }

        // Process COMMENT analytics
        foreach ($commentsMetrics as $commentId => $metrics) {
            $comment = $commentRepo->find($commentId);
            if ($comment) {
                if (isset($metrics['readTime'])) {
                    $comment->setTotalReadTime(min(999999999, ($comment->getTotalReadTime() ?: 0) + $metrics['readTime']));
                }
            }
        }

        $em->flush();

        return $this->json(['success' => true]);
    }
}

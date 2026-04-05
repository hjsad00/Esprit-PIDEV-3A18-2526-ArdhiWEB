<?php

namespace App\Service\UserAndDiag;

use App\Entity\UserAndDiag\User;
use App\Entity\UserAndDiag\Badge;
use App\Entity\UserAndDiag\UserBadge;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GamificationService
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
    }

    public function addPoints(User $user, int $amount): void
    {
        try {
            // Update points
            $user->setPoints($user->getPoints() + $amount);

            // Level = 1 + Points / 500
            $newLevel = 1 + floor($user->getPoints() / 500);
            $user->setLevel((int) $newLevel);

            $this->em->persist($user);
            $this->em->flush();

            // Check point-based badges
            $this->checkPointBadges($user);
        } catch (\Exception $e) {
            $this->logger->error("Error adding points: " . $e->getMessage());
        }
    }

    public function checkDiagnosticBadges(User $user): void
    {
        try {
            // Count diagnostics
            $count = $this->em->createQueryBuilder()
                ->select('COUNT(d.id)')
                ->from('App\Entity\UserAndDiag\Diagnostic', 'd')
                ->where('d.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            $this->checkBadges($user, 'DIAGNOSTIC', (int) $count);
        } catch (\Exception $e) {
            $this->logger->error("Error checking diagnostic badges: " . $e->getMessage());
        }
    }

    public function checkPointBadges(User $user): void
    {
        try {
            $this->checkBadges($user, 'POINTS', $user->getPoints());
        } catch (\Exception $e) {
            $this->logger->error("Error checking point badges: " . $e->getMessage());
        }
    }

    public function checkHealthyBadges(User $user): void
    {
        try {
            // Assuming 'saine' is the keyword in resultat_ia for healthy plants
            $count = $this->em->createQueryBuilder()
                ->select('COUNT(d.id)')
                ->from('App\Entity\UserAndDiag\Diagnostic', 'd')
                ->where('d.user = :user')
                ->andWhere('LOWER(d.resultat_ia) LIKE :saine')
                ->setParameter('user', $user)
                ->setParameter('saine', '%saine%')
                ->getQuery()
                ->getSingleScalarResult();

            $this->checkBadges($user, 'HEALTHY_PLANTS', (int) $count);
        } catch (\Exception $e) {
            $this->logger->error("Error checking healthy badges: " . $e->getMessage());
        }
    }

    public function checkSolutionBadges(User $user): void
    {
        try {
            // Count accepted solutions in CommunityComment
            // Assuming the old logic counted all comments, but let's check for 'solution = true' if that's the intent.
            // The Java code did: SELECT COUNT(*) FROM community_comments WHERE user_id = ?
            // I'll stick to the Java logic: count all comments.
            $count = $this->em->createQueryBuilder()
                ->select('COUNT(c.id)')
                ->from('App\Entity\UserAndDiag\CommunityComment', 'c')
                ->where('c.user = :user')
                ->andWhere('c.is_solution = true')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            $this->checkBadges($user, 'SOLUTION', (int) $count);
        } catch (\Exception $e) {
            $this->logger->error("Error checking solution badges: " . $e->getMessage());
        }
    }

    /**
     * Finds badges of this type that the user meets the threshold for, but doesn't have yet.
     */
    private function checkBadges(User $user, string $type, int $value): void
    {
        try {
            // 1. Get all badges of this type where threshold <= value
            $eligibleBadges = $this->em->createQueryBuilder()
                ->select('b')
                ->from(Badge::class, 'b')
                ->where('b.condition_type = :type')
                ->andWhere('b.threshold <= :val')
                ->setParameter('type', $type)
                ->setParameter('val', $value)
                ->getQuery()
                ->getResult();

            if (empty($eligibleBadges)) {
                return;
            }

            // 2. Get user's current badges of this type
            $userBadges = $this->em->createQueryBuilder()
                ->select('IDENTITY(ub.badge)')
                ->from(UserBadge::class, 'ub')
                ->join('ub.badge', 'b')
                ->where('ub.user = :user')
                ->andWhere('b.condition_type = :type')
                ->setParameter('user', $user)
                ->setParameter('type', $type)
                ->getQuery()
                ->getSingleColumnResult();

            // 3. Award new badges
            $flushNeeded = false;
            foreach ($eligibleBadges as $badge) {
                if (!in_array($badge->getId(), $userBadges)) {
                    $ub = new UserBadge();
                    $ub->setUser($user);
                    $ub->setBadge($badge);
                    // $ub->setAcquiredAt(new \DateTime()); // Handled in constructor

                    $this->em->persist($ub);
                    $flushNeeded = true;

                    $this->logger->info("Badge Unlocked! User: " . $user->getId() . " Badge: " . $badge->getName() . " (ID: " . $badge->getId() . ")");
                }
            }

            if ($flushNeeded) {
                $this->em->flush();
            }

        } catch (\Exception $e) {
            $this->logger->error("Error in checkBadges: " . $e->getMessage());
        }
    }

    public function getLeaderboard(int $limit = 10): array
    {
        try {
            return $this->em->createQueryBuilder()
                ->select('u.id, u.nom, u.prenom, u.points, u.level')
                ->from(User::class, 'u')
                ->where('u.role != :adminRole')
                ->setParameter('adminRole', 'ADMIN')
                ->orderBy('u.points', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            $this->logger->error("Error fetching leaderboard: " . $e->getMessage());
            return [];
        }
    }

    public function getUserStats(User $user): array
    {
        return [
            'id' => $user->getId(),
            'points' => $user->getPoints(),
            'level' => $user->getLevel(),
        ];
    }

    public function getUserBadges(User $user): array
    {
        try {
            return $this->em->createQueryBuilder()
                ->select('b.icon, b.name, b.description')
                ->from(UserBadge::class, 'ub')
                ->join('ub.badge', 'b')
                ->where('ub.user = :user')
                ->orderBy('ub.acquired_at', 'DESC')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            $this->logger->error("Error fetching user badges: " . $e->getMessage());
            return [];
        }
    }
}

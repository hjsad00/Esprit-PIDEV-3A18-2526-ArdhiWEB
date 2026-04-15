<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\CommunityReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommunityReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityReport::class);
    }

    /**
     * Returns unresolved reports grouped by post, ordered by report count DESC.
     */
    public function findUnresolvedGroupedByPost(): array
    {
        return $this->createQueryBuilder('r')
            ->select('IDENTITY(r.post) as post_id, COUNT(r.id) as report_count')
            ->where('r.is_resolved = false')
            ->andWhere('r.post IS NOT NULL')
            ->groupBy('r.post')
            ->orderBy('report_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a user already reported a specific post.
     */
    public function hasUserReportedPost($user, $post): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.reporter = :user')
            ->andWhere('r.post = :post')
            ->setParameter('user', $user)
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}

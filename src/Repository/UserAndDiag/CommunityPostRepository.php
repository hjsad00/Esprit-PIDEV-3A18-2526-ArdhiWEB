<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\CommunityPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityPost>
 */
class CommunityPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityPost::class);
    }

    /**
     * @return CommunityPost[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return CommunityPost[]
     */
    public function searchByKeyword(string $keyword): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.title LIKE :kw')
            ->orWhere('p.description LIKE :kw')
            ->setParameter('kw', '%' . $keyword . '%')
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\CommunityPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityPost>
 *
 * @method CommunityPost|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityPost|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityPost[]    findAll()
 * @method CommunityPost[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityPost::class);
    }
}
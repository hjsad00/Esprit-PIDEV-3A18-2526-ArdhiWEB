<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\CommunityLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityLike>
 *
 * @method CommunityLike|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityLike|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityLike[]    findAll()
 * @method CommunityLike[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityLike::class);
    }
}
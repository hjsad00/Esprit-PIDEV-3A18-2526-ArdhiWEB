<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\CouponUtilisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CouponUtilisation>
 */
class CouponUtilisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CouponUtilisation::class);
    }
}

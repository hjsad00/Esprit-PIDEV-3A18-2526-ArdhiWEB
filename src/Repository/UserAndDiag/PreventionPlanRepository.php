<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\PreventionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreventionPlan>
 *
 * @method PreventionPlan|null find($id, $lockMode = null, $lockVersion = null)
 * @method PreventionPlan|null findOneBy(array $criteria, array $orderBy = null)
 * @method PreventionPlan[]    findAll()
 * @method PreventionPlan[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreventionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreventionPlan::class);
    }
}
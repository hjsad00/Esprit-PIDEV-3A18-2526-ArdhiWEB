<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\PreventionTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreventionTask>
 *
 * @method PreventionTask|null find($id, $lockMode = null, $lockVersion = null)
 * @method PreventionTask|null findOneBy(array $criteria, array $orderBy = null)
 * @method PreventionTask[]    findAll()
 * @method PreventionTask[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreventionTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreventionTask::class);
    }
}
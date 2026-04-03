<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\TreatmentTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TreatmentTask>
 *
 * @method TreatmentTask|null find($id, $lockMode = null, $lockVersion = null)
 * @method TreatmentTask|null findOneBy(array $criteria, array $orderBy = null)
 * @method TreatmentTask[]    findAll()
 * @method TreatmentTask[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TreatmentTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TreatmentTask::class);
    }
}

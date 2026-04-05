<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\TreatmentPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TreatmentPlan>
 *
 * @method TreatmentPlan|null find($id, $lockMode = null, $lockVersion = null)
 * @method TreatmentPlan|null findOneBy(array $criteria, array $orderBy = null)
 * @method TreatmentPlan[]    findAll()
 * @method TreatmentPlan[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TreatmentPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TreatmentPlan::class);
    }

    /**
     * @return TreatmentPlan[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('tp')
            ->join('tp.diagnostic', 'd')
            ->where('d.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('tp.start_date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

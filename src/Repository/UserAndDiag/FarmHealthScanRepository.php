<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\FarmHealthScan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FarmHealthScan>
 *
 * @method FarmHealthScan|null find($id, $lockMode = null, $lockVersion = null)
 * @method FarmHealthScan|null findOneBy(array $criteria, array $orderBy = null)
 * @method FarmHealthScan[]    findAll()
 * @method FarmHealthScan[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FarmHealthScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FarmHealthScan::class);
    }
}
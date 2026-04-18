<?php

namespace App\Repository\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\NotificationMaintenance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationMaintenance>
 */
class NotificationMaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationMaintenance::class);
    }
}

<?php

namespace App\Repository\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Maintenance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Maintenance>
 */
class MaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Maintenance::class);
    }

    public function searchByUser(int $userId, ?string $type = null, ?string $statut = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->join('m.materiel', 'mat')
            ->andWhere('mat.userId = :userId')
            ->setParameter('userId', $userId);

        if ($type) {
            $qb->andWhere('m.type_maintenance = :type')
               ->setParameter('type', $type);
        }

        if ($statut) {
            $qb->andWhere('m.statut_maintenance = :statut')
               ->setParameter('statut', $statut);
        }

        if ($search) {
            $qb->andWhere('mat.nom LIKE :search OR m.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('m.date_maintenance', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsByUser(int $userId): array
    {
        $total = $this->createQueryBuilder('m')
            ->select('count(m.id_maintenance)')
            ->join('m.materiel', 'mat')
            ->andWhere('mat.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        $enCours = $this->createQueryBuilder('m')
            ->select('count(m.id_maintenance)')
            ->join('m.materiel', 'mat')
            ->andWhere('mat.userId = :userId')
            ->andWhere('m.statut_maintenance = :statut')
            ->setParameter('userId', $userId)
            ->setParameter('statut', 'en_cours')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'en_cours' => $enCours,
        ];
    }

    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.date_maintenance', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

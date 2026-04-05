<?php

namespace App\Repository\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Materiel>
 */
class MaterielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Materiel::class);
    }

    public function searchByUser(int $userId, ?string $search = null, ?string $type = null, ?string $etat = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.userId = :userId')
            ->setParameter('userId', $userId);

        if ($search) {
            $qb->andWhere('m.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type) {
            $qb->andWhere('m.type = :type')
               ->setParameter('type', $type);
        }

        if ($etat) {
            $qb->andWhere('m.etat = :etat')
               ->setParameter('etat', $etat);
        }

        return $qb->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsByUser(int $userId): array
    {
        $total = $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->andWhere('m.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        $enPanne = $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->andWhere('m.userId = :userId')
            ->andWhere('m.etat = :etat')
            ->setParameter('userId', $userId)
            ->setParameter('etat', 'En panne')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'en_panne' => $enPanne,
        ];
    }
    
    public function findEnRetardByUser(int $userId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.userId = :userId')
            ->andWhere('m.dateProchaineMaintenance < :now')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }
}

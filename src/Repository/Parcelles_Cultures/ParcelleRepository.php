<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parcelle>
 */
class ParcelleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcelle::class);
    }

    public function findByAgriculteur(User $user)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $user)
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByAgriculteur(User $user): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getSurfaceTotalByAgriculteur(User $user): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.surface) as total_surface')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return (float) ($result['total_surface'] ?? 0);
    }

    public function getStatsByAgriculteur(User $user): array
    {
        return [
            'total_parcelles' => $this->countByAgriculteur($user),
            'surface_totale' => $this->getSurfaceTotalByAgriculteur($user),
            'parcelles_actives' => $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.agriculteur = :agriculteur')
                ->andWhere('p.statut = :statut')
                ->setParameter('agriculteur', $user)
                ->setParameter('statut', 'active')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }
}

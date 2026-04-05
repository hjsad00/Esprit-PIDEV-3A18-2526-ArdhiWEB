<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Parcelle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parcelle>
 */
class ParceleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcelle::class);
    }

    /**
     * Récupère toutes les parcelles d'un agriculteur
     */
    public function findByAgriculteur($agriculteurId)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $agriculteurId)
            ->orderBy('p.localisation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte la surface totale des parcelles d'un agriculteur
     */
    public function getTotalSurfaceByAgriculteur($agriculteurId): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.surface) as total')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $agriculteurId)
            ->getQuery()
            ->getSingleResult();
        
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Getql les parcelles par statut
     */
    public function findByStatut(string $statut)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('p.localisation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par localisation (LIKE)
     */
    public function findByLocalisation(string $localisation)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.localisation LIKE :loc')
            ->setParameter('loc', '%' . $localisation . '%')
            ->orderBy('p.localisation', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

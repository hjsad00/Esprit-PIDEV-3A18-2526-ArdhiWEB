<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function findWithFilters(?string $type, ?string $statut, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')->orderBy('e.dateDebut', 'ASC');
        if ($type)   { $qb->andWhere('e.type = :type')->setParameter('type', $type); }
        if ($statut) { $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut); }
        if ($search) {
            $qb->andWhere('e.titre LIKE :s OR e.lieu LIKE :s OR e.organisateur LIKE :s')
               ->setParameter('s', '%'.$search.'%');
        }
        return $qb->getQuery()->getResult();
    }

    public function findByCreateurWithFilters($createur, ?string $type, ?string $statut, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.createur = :createur')
            ->setParameter('createur', $createur)
            ->orderBy('e.dateDebut', 'ASC');

        if ($type) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }
        if ($statut) {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut);
        }
        if ($search) {
            $qb->andWhere('e.titre LIKE :s OR e.lieu LIKE :s OR e.organisateur LIKE :s')
               ->setParameter('s', '%'.$search.'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Events eligible for automatic status sync.
     */
    public function findForStatusSync(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut != :annule')
            ->andWhere('e.dateDebut IS NOT NULL')
            ->andWhere('e.dateFin IS NOT NULL')
            ->setParameter('annule', 'ANNULE')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all events that have the given statut value.
     * Used by ParticipationPredictionService to analyse historical data.
     *
     * @return Evenement[]
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('e.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

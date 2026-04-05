<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\CreditDossier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreditDossier>
 */
class CreditDossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditDossier::class);
    }

    /**
     * Récupère les dossiers crédit d'une parcelle
     */
    public function findByParcelle($parcelleId)
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelleId)
            ->orderBy('cd.date_creation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les dossiers crédit d'un utilisateur
     */
    public function findByUser($userId)
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.user = :user')
            ->setParameter('user', $userId)
            ->orderBy('cd.date_creation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les dossiers crédit d'une parcelle pour un utilisateur donné
     */
    public function findByParcelleAndUser($parcelleId, $userId)
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.parcelle = :parcelle')
            ->andWhere('cd.user = :user')
            ->setParameter('parcelle', $parcelleId)
            ->setParameter('user', $userId)
            ->orderBy('cd.date_creation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le dossier crédit le plus récent pour une parcelle
     */
    public function findLatestByParcelle($parcelleId): ?CreditDossier
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelleId)
            ->orderBy('cd.date_creation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les dossiers par niveau de risque
     */
    public function findByNiveauRisque(string $niveau)
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.niveau_risque = :niveau')
            ->setParameter('niveau', $niveau)
            ->orderBy('cd.score_risque', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les dossiers exportés
     */
    public function findExported()
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.date_export IS NOT NULL')
            ->orderBy('cd.date_export', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

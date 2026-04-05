<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Culture>
 */
class CultureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Culture::class);
    }

    /**
     * Récupère toutes les cultures d'une parcelle
     */
    public function findByParcelle($parcelleId)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelleId)
            ->orderBy('c.date_plantation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les cultures prêtes à récolter pour un agriculteur
     * (date du jour >= date_recolte_prevue ET etat != 'Récoltée')
     */
    public function getCulturesPretesARecolter($agriculteurId)
    {
        $today = new \DateTime();
        return $this->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->andWhere('p.agriculteur = :agriculteur')
            ->andWhere('c.date_recolte_prevue <= :today')
            ->andWhere('c.etat_culture != :etat_recoltee')
            ->setParameter('agriculteur', $agriculteurId)
            ->setParameter('today', $today)
            ->setParameter('etat_recoltee', 'Récoltée')
            ->orderBy('c.date_recolte_prevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère la surface utilisée totale pour une parcelle
     */
    public function getSurfaceUtiliseeTotalByParcelle($parcelleId): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.surface_utilisee) as total')
            ->andWhere('c.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelleId)
            ->getQuery()
            ->getSingleResult();
        
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Récupère les cultures actives (non récoltées) pour un agriculteur
     */
    public function getActiveByAgriculteur($agriculteurId)
    {
        return $this->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->andWhere('p.agriculteur = :agriculteur')
            ->andWhere('c.etat_culture != :etat')
            ->setParameter('agriculteur', $agriculteurId)
            ->setParameter('etat', 'Récoltée')
            ->orderBy('c.date_recolte_prevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les cultures par type
     */
    public function findByType(string $type)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type_culture = :type')
            ->setParameter('type', $type)
            ->orderBy('c.date_plantation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques d'une parcelle: rendement total estimé
     */
    public function getRendementEstimeTotalByParcelle($parcelleId): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.surface_utilisee * c.rendement_estime) as total')
            ->andWhere('c.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelleId)
            ->getQuery()
            ->getSingleResult();
        
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Récupère les statistiques par saison pour un agriculteur
     */
    public function getStatsBySeasonForAgriculteur($agriculteurId)
    {
        return $this->createQueryBuilder('c')
            ->select('c.saison, COUNT(c.id) as nb_cultures, SUM(c.surface_utilisee) as surface_total, AVG(c.rendement_estime) as rendement_moyen')
            ->join('c.parcelle', 'p')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $agriculteurId)
            ->groupBy('c.saison')
            ->getQuery()
            ->getResult();
    }
}

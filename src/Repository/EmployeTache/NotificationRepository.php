<?php

namespace App\Repository\EmployeTache;

use App\Entity\EmployeTache\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Toutes les notifications d'un agriculteur, du plus récent au plus ancien.
     *
     * @return Notification[]
     */
    public function findByAgriculteur(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->orderBy('n.dateCreation', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Notifications non lues d'un agriculteur.
     *
     * @return Notification[]
     */
    public function findUnreadByAgriculteur(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.idAgriculteur = :agri')
            ->andWhere('n.lue = false')
            ->setParameter('agri', $idAgriculteur)
            ->orderBy('n.dateCreation', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les notifications non lues (pour le badge navbar).
     */
    public function countUnread(int $idAgriculteur): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.idAgriculteur = :agri')
            ->andWhere('n.lue = false')
            ->setParameter('agri', $idAgriculteur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Anti-spam : vérifie si une notif du même type pour la même tâche existe déjà aujourd'hui.
     */
  public function existsTodayForTache(string $type, int $idTache, int $idAgriculteur): bool
{
    $today = new \DateTime('today');
    $tomorrow = (clone $today)->modify('+1 day');
 
    $count = $this->createQueryBuilder('n')
        ->select('COUNT(n.id)')
        ->where('n.type = :type')
        ->andWhere('n.idTache = :tache')
        ->andWhere('n.idAgriculteur = :agri')
        ->andWhere('n.dateCreation >= :today')
        ->andWhere('n.dateCreation < :tomorrow')
        ->setParameter('type', $type)
        ->setParameter('tache', $idTache)
        ->setParameter('agri', $idAgriculteur)
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->getQuery()
        ->getSingleScalarResult();
 
    return $count > 0;
}
public function existsTodayGlobal(string $type, int $idAgriculteur): bool
{
    $today    = new \DateTime('today');
    $tomorrow = (clone $today)->modify('+1 day');
 
    $count = $this->createQueryBuilder('n')
        ->select('COUNT(n.id)')
        ->where('n.type = :type')
        ->andWhere('n.idAgriculteur = :agri')
        ->andWhere('n.idTache IS NULL')   // Notification générale (sans tâche)
        ->andWhere('n.dateCreation >= :today')
        ->andWhere('n.dateCreation < :tomorrow')
        ->setParameter('type', $type)
        ->setParameter('agri', $idAgriculteur)
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->getQuery()
        ->getSingleScalarResult();
 
    return $count > 0;
}

    /**
     * Anti-spam général (sans filtre par tâche) — pour les alertes globales.
     */
    public function existsTodayForAgriculteur(string $type, int $idAgriculteur): bool
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $count = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.type = :type')
            ->andWhere('n.idAgriculteur = :agri')
            ->andWhere('n.dateCreation >= :today')
            ->andWhere('n.dateCreation < :tomorrow')
            ->setParameter('type', $type)
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
    /**
     * @return Notification[]
     */
    public function findTachesDuJour(int $idAgriculteur): array
    {
        $today = new \DateTime('today');
    
        return $this->createQueryBuilder('t')
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.statut IN (:statuts)')
            ->andWhere('t.dateDebut <= :today OR t.dateDebut IS NULL')
            ->andWhere('t.dateFin >= :today OR t.dateFin IS NULL')
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('statuts', ['En cours', 'En attente'])
            ->setParameter('today', $today)
            ->orderBy('t.priorite', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les notifications obsolètes d'une tâche terminée.
     */
    public function deleteByTache(int $idTache): void
    {
        $this->createQueryBuilder('n')
            ->delete()
            ->where('n.idTache = :tache')
            ->setParameter('tache', $idTache)
            ->getQuery()
            ->execute();
    }
}
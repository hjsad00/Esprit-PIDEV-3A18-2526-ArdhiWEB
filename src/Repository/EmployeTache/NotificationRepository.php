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
     */
    public function findByAgriculteur(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Notifications non lues d'un agriculteur.
     */
    public function findUnreadByAgriculteur(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.idAgriculteur = :agri')
            ->andWhere('n.lue = false')
            ->setParameter('agri', $idAgriculteur)
            ->orderBy('n.dateCreation', 'DESC')
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
        $tomorrow = new \DateTime('tomorrow');

        $count = (int) $this->createQueryBuilder('n')
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
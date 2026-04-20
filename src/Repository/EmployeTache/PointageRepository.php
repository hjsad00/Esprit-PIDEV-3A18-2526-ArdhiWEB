<?php

namespace App\Repository\EmployeTache;

use App\Entity\EmployeTache\Pointage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PointageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pointage::class);
    }

    /**
     * Retourne tous les pointages d'un agriculteur pour une date donnée.
     */
    public function findByAgriculteurDate(int $idAgriculteur, \DateTime $date): array
    {
        $debut = (clone $date)->setTime(0, 0, 0);
        $fin   = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->where('p.idAgriculteur = :agri')
            ->andWhere('p.dateHeure >= :debut')
            ->andWhere('p.dateHeure <= :fin')
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Stats de présence par jour sur N derniers jours.
     * Retourne un tableau [ ['date' => '2024-01-15', 'nb' => 4], ... ]
     *
     * Utilise du SQL natif car DATE() n'est pas supporté nativement en DQL.
     */
    public function statsParJour(int $idAgriculteur, int $nbJours = 7): array
    {
        $depuis = new \DateTime("-{$nbJours} days");

        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(date_heure) AS date, COUNT(DISTINCT id_employe) AS nb
            FROM pointage
            WHERE id_agriculteur = :agri
              AND date_heure >= :depuis
              AND type = :type
              AND valide = 1
            GROUP BY DATE(date_heure)
            ORDER BY date ASC
        ';

        $result = $conn->executeQuery($sql, [
            'agri'   => $idAgriculteur,
            'depuis' => $depuis->format('Y-m-d H:i:s'),
            'type'   => Pointage::TYPE_ARRIVEE,
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * Retourne les derniers pointages d'un employé (toutes dates).
     */
    public function findDerniersPointagesEmploye(int $idEmploye, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.idEmploye = :emp')
            ->setParameter('emp', $idEmploye)
            ->orderBy('p.dateHeure', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le dernier pointage d'un employé pour aujourd'hui.
     */
    public function findDernierPointageAujourdhui(int $idEmploye): ?Pointage
    {
        $debut = (new \DateTime('today'))->setTime(0, 0, 0);
        $fin   = (new \DateTime('today'))->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->where('p.idEmploye = :emp')
            ->andWhere('p.dateHeure >= :debut')
            ->andWhere('p.dateHeure <= :fin')
            ->setParameter('emp', $idEmploye)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.dateHeure', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
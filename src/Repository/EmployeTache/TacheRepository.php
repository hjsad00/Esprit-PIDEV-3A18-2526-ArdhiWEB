<?php

namespace App\Repository\EmployeTache;

use App\Entity\EmployeTache\Tache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tache>
 */
class TacheRepository extends ServiceEntityRepository
{
    // Champs de tri autorisés — identiques aux CRITERES_TRI du desktop
    private const CHAMPS_TRI = [
        'id'         => 't.id',
        'titre'      => 't.titre',
        'statut'     => 't.statut',
        'priorite'   => 't.priorite',
        'dateDebut'  => 't.dateDebut',
        'dateFin'    => 't.dateFin',
        'categorie'  => 't.categorie',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tache::class);
    }

    /**
     * Requête principale avec filtres + tri
     *
     * @return Tache[]
     */
    public function findFiltreeTrie(
        int     $idAgriculteur,
        string  $search    = '',
        string  $statut    = 'Tous',
        string  $priorite  = 'Toutes',
        string  $categorie = 'Toutes',
        string  $tri       = 'dateDebut',
        string  $direction = 'asc'
    ): array {
        $champ = self::CHAMPS_TRI[$tri] ?? 't.dateDebut';
        $dir   = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('t')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur);

        if ($search !== '') {
            if (is_numeric($search)) {
                $qb->andWhere('t.id = :sid OR t.titre LIKE :s OR t.description LIKE :s OR t.categorie LIKE :s')
                   ->setParameter('sid', (int) $search)
                   ->setParameter('s', '%' . $search . '%');
            } else {
                $qb->andWhere('t.titre LIKE :s OR t.description LIKE :s OR t.categorie LIKE :s')
                   ->setParameter('s', '%' . $search . '%');
            }
        }

        if ($statut !== 'Tous') {
            $qb->andWhere('t.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($priorite !== 'Toutes') {
            $map = ['Basse' => 1, 'Moyenne' => 2, 'Haute' => 3, 'Critique' => 4];
            if (isset($map[$priorite])) {
                $qb->andWhere('t.priorite = :priorite')
                   ->setParameter('priorite', $map[$priorite]);
            }
        }

        if ($categorie !== 'Toutes') {
            $qb->andWhere('t.categorie = :categorie')
               ->setParameter('categorie', $categorie);
        }

        $qb->orderBy($champ, $dir);

        return $qb->getQuery()->getResult();
    }

    /**
     * ✅ NOUVEAU — Compte les tâches actives (En attente ou En cours) d'un employé.
     * Utilisé par EmployeAutoInactifService pour décider d'activer/désactiver l'employé.
     */
    public function countTachesActivesParEmploye(int $idEmploye, int $idAgriculteur): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.idEmploye = :emp')
            ->andWhere('t.idAgriculteur = :agri')
            ->andWhere('t.statut IN (:actifs)')
            ->setParameter('emp', $idEmploye)
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('actifs', ['En attente', 'En cours'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ Batch anti-N+1 pour compter les tâches actives de TOUS les employés
     * @return \App\DTO\EmployeTache\CountDTO[]
     */
    public function countTachesActivesBatch(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('t')
            ->select('NEW App\DTO\EmployeTache\CountDTO(t.idEmploye, COUNT(t.id))')
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.statut IN (:actifs)')
            ->andWhere('t.idEmploye IS NOT NULL')
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('actifs', ['En attente', 'En cours'])
            ->groupBy('t.idEmploye')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les tâches par statut pour les KPIs du header
     *
     * @return array{total: int, en_cours: int, terminees: int, en_attente: int, annulees: int}
     */
    public function countByStatut(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('NEW App\DTO\EmployeTache\CountDTO(t.statut, COUNT(t.id))')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.statut')
            ->getQuery()
            ->getResult();

        $counts = [
            'total'      => 0,
            'en_cours'   => 0,
            'terminees'  => 0,
            'en_attente' => 0,
            'annulees'   => 0,
        ];

        /** @var \App\DTO\EmployeTache\CountDTO $r */
        foreach ($results as $r) {
            $counts['total'] += (int) $r->total;
            match($r->key) {
                'En cours'   => $counts['en_cours']   += (int) $r->total,
                'Terminé'    => $counts['terminees']  += (int) $r->total,
                'Validé'     => $counts['terminees']  += (int) $r->total,
                'En attente' => $counts['en_attente'] += (int) $r->total,
                'Annulé'     => $counts['annulees']   += (int) $r->total,
                default      => null,
            };
        }

        return $counts;
    }

    /**
     * @return Tache[]
     */
    public function findByEmploye(int $idEmploye, int $idAgriculteur): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.idEmploye = :emp')
            ->andWhere('t.idAgriculteur = :agri')
            ->setParameter('emp', $idEmploye)
            ->setParameter('agri', $idAgriculteur)
            ->orderBy('t.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tache[]
     */
    public function findByAgriculteur(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->orderBy('t.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tache[]
     */
    public function findTachesDuJour(int $idAgriculteur): array
    {
        $today = new \DateTime('today');

        return $this->createQueryBuilder('t')
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.dateDebut <= :today')
            ->andWhere('t.dateFin >= :today OR t.dateFin IS NULL')
            ->andWhere('t.statut NOT IN (:finished)')
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('today', $today)
            ->setParameter('finished', ['Terminé', 'Validé', 'Annulé'])
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int>
     */
    public function countByPriorite(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('NEW App\DTO\EmployeTache\CountDTO(t.priorite, COUNT(t.id))')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.priorite')
            ->getQuery()
            ->getResult();

        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        /** @var \App\DTO\EmployeTache\CountDTO $r */
        foreach ($results as $r) {
            $counts[(int)$r->key] = (int)$r->total;
        }
        return $counts;
    }

    /**
     * @return \App\DTO\EmployeTache\CountDTO[]
     */
    public function countByEmploye(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('t')
            ->select('NEW App\DTO\EmployeTache\CountDTO(t.idEmploye, COUNT(t.id))')
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.idEmploye IS NOT NULL')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.idEmploye')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return \App\DTO\EmployeTache\CountDTO[]
     */
    public function countByDate(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('t')
            ->select("NEW App\DTO\EmployeTache\CountDTO(t.dateDebut, COUNT(t.id))")
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.dateDebut IS NOT NULL')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.dateDebut')
            ->orderBy('t.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByCategorie(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('NEW App\DTO\EmployeTache\CountDTO(t.categorie, COUNT(t.id))')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.categorie')
            ->getQuery()
            ->getResult();

        $counts = [];
        /** @var \App\DTO\EmployeTache\CountDTO $r */
        foreach ($results as $r) {
            $counts[$r->key ?? 'Autre'] = (int)$r->total;
        }
        return $counts;
    }

    public function countEnRetard(int $idAgriculteur): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.dateFin < :today')
            ->andWhere('t.statut NOT IN (:finished)')
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('today', new \DateTime('today'))
            ->setParameter('finished', ['Terminé', 'Validé', 'Annulé'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNonAssignees(int $idAgriculteur): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.idEmploye IS NULL')
            ->setParameter('agri', $idAgriculteur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countDetailStatut(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('NEW App\DTO\EmployeTache\CountDTO(t.statut, COUNT(t.id))')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.statut')
            ->getQuery()
            ->getResult();

        $counts = [
            'En attente' => 0,
            'En cours'   => 0,
            'Terminé'    => 0,
            'Validé'     => 0,
            'Annulé'     => 0,
        ];
        /** @var \App\DTO\EmployeTache\CountDTO $r */
        foreach ($results as $r) {
            if (isset($counts[$r->key])) {
                $counts[$r->key] = (int)$r->total;
            }
        }
        return $counts;
    }
    /**
     * @return Tache[]
     */
    public function findTachesParEmployePourPerformance(int $idEmploye): array
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->where('t.idEmploye = :emp')
            ->setParameter('emp', $idEmploye)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Récupère les tâches terminées pour calculer l'historique de risque.
     *
     * @return Tache[]
     */
    public function findHistoriquePourRisque(int $idEmploye): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.idEmploye = :emp')
            ->andWhere('t.statut IN (:finished)')
            ->setParameter('emp', $idEmploye)
            ->setParameter('finished', ['Terminé', 'Validé', 'Annulé'])
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Compte les tâches actives pour évaluer la charge de travail (en excluant la tâche courante).
     */
    public function countChargeActuelle(int $idEmploye, int $excludeTacheId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.idEmploye = :emp')
            ->andWhere('t.id != :exclude')
            ->andWhere('t.statut IN (:actives)')
            ->setParameter('emp', $idEmploye)
            ->setParameter('exclude', $excludeTacheId)
            ->setParameter('actives', ['En attente', 'En cours'])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
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
     * Identique à la combinaison FilteredList + SortedList + ComboBox du desktop
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

        // Recherche texte (titre, description, id_employe)
        if ($search !== '') {
            $qb->andWhere('t.titre LIKE :s OR t.description LIKE :s OR t.categorie LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }

        // Filtre statut
        if ($statut !== 'Tous') {
            $qb->andWhere('t.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // Filtre priorité (convertit le label en valeur numérique)
        if ($priorite !== 'Toutes') {
            $map = ['Basse' => 1, 'Moyenne' => 2, 'Haute' => 3, 'Critique' => 4];
            if (isset($map[$priorite])) {
                $qb->andWhere('t.priorite = :priorite')
                   ->setParameter('priorite', $map[$priorite]);
            }
        }

        // Filtre catégorie
        if ($categorie !== 'Toutes') {
            $qb->andWhere('t.categorie = :categorie')
               ->setParameter('categorie', $categorie);
        }

        $qb->orderBy($champ, $dir);

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les tâches par statut pour les KPIs du header
     */
    public function countByStatut(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('t.statut, COUNT(t.id) as total')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.statut')
            ->getQuery()
            ->getResult();

        $counts = [
            'total'     => 0,
            'en_cours'  => 0,
            'terminees' => 0,
            'en_attente'=> 0,
            'annulees'  => 0,
        ];

        foreach ($results as $r) {
            $counts['total'] += $r['total'];
            match($r['statut']) {
                'En cours'  => $counts['en_cours']   += $r['total'],
                'Terminé'   => $counts['terminees']  += $r['total'],
                'Validé'    => $counts['terminees']  += $r['total'],
                'En attente'=> $counts['en_attente'] += $r['total'],
                'Annulé'    => $counts['annulees']   += $r['total'],
                default     => null,
            };
        }

        return $counts;
    }

    /**
     * Récupère toutes les tâches d'un employé spécifique
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
     * Toutes les tâches d'un agriculteur (pour PDF)
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
     * Récupère les tâches prévues pour aujourd'hui
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
     * Compte les tâches par priorité — pour les statistiques
     */
    public function countByPriorite(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('t.priorite, COUNT(t.id) as total')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.priorite')
            ->getQuery()
            ->getResult();

        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($results as $r) {
            $counts[(int)$r['priorite']] = (int)$r['total'];
        }
        return $counts;
    }

    /**
     * Compte les tâches par employé — pour les statistiques
     */
    public function countByEmploye(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.idEmploye, COUNT(t.id) as total')
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.idEmploye IS NOT NULL')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.idEmploye')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les tâches par date de création (dateDebut) — pour l'évolution
     */
    public function countByDate(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('t')
            ->select("t.dateDebut, COUNT(t.id) as total")
            ->where('t.idAgriculteur = :agri')
            ->andWhere('t.dateDebut IS NOT NULL')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.dateDebut')
            ->orderBy('t.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les tâches par catégorie — pour les statistiques
     */
    public function countByCategorie(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('t.categorie, COUNT(t.id) as total')
            ->where('t.idAgriculteur = :agri')
            ->setParameter('agri', $idAgriculteur)
            ->groupBy('t.categorie')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $r) {
            $counts[$r['categorie'] ?? 'Autre'] = (int)$r['total'];
        }
        return $counts;
    }

    /**
     * Compte les tâches en retard
     */
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

    /**
     * Compte les tâches non assignées
     */
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
     * Détail des statuts avec comptage individuel — pour les statistiques
     */
    public function countDetailStatut(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('t.statut, COUNT(t.id) as total')
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
        foreach ($results as $r) {
            if (isset($counts[$r['statut']])) {
                $counts[$r['statut']] = (int)$r['total'];
            }
        }
        return $counts;
    }
}
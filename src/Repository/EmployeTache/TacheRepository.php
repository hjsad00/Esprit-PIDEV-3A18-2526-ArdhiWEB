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
}
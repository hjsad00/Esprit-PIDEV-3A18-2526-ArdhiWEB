<?php

namespace App\Repository\EmployeTache;

use App\Entity\EmployeTache\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employe>
 */
class EmployeRepository extends ServiceEntityRepository
{
    // Champs autorisés pour le tri (sécurité — évite l'injection SQL)
    private const CHAMPS_TRI_AUTORISES = [
        'id'        => 'e.id',
        'nom'       => 'e.nom',
        'prenom'    => 'e.prenom',
        'email'     => 'e.email',
        'poste'     => 'e.poste',
        'actif'     => 'e.actif',
        'telephone' => 'e.telephone',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employe::class);
    }

    /**
     * Tous les employés d'un agriculteur avec tri + recherche
     * Identique au SortedList + FilteredList du desktop JavaFX
     *
     * @return Employe[]
     */
    public function findByAgriculteurTrie(
        int    $idAgriculteur,
        string $tri       = 'nom',
        string $direction = 'asc',
        string $search    = ''
    ): array {
        // Champ de tri sécurisé (whitelist)
        $champ = self::CHAMPS_TRI_AUTORISES[$tri] ?? 'e.nom';
        // Direction sécurisée
        $dir   = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('e')
            ->where('e.idAgriculteur = :id')
            ->setParameter('id', $idAgriculteur);

        // Recherche (identique au listener txtRecherche du desktop)
        if ($search !== '') {
            $qb->andWhere(
                'e.nom LIKE :s OR e.prenom LIKE :s OR e.email LIKE :s
                 OR e.poste LIKE :s OR e.telephone LIKE :s'
            )->setParameter('s', '%' . $search . '%');
        }

        // Tri actif : les actifs d'abord si tri=actif
        if ($tri === 'actif') {
            $qb->orderBy($champ, 'DESC'); // TRUE (1) avant FALSE (0)
        } else {
            $qb->orderBy($champ, $dir);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Alias simple sans tri (utilisé en interne)
     *
     * @return Employe[]
     */
    public function findByAgriculteur(int $idAgriculteur): array
    {
        return $this->findByAgriculteurTrie($idAgriculteur);
    }

    /**
     * Uniquement les employés ACTIFS d'un agriculteur
     *
     * @return Employe[]
     */
    public function findActifsByAgriculteur(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.idAgriculteur = :id')
            ->andWhere('e.actif = true')
            ->setParameter('id', $idAgriculteur)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par nom / prénom / email / poste / téléphone
     *
     * @return Employe[]
     */
    public function search(string $terme, int $idAgriculteur,
                           string $tri = 'nom', string $direction = 'asc'): array
    {
        return $this->findByAgriculteurTrie($idAgriculteur, $tri, $direction, $terme);
    }

    /**
     * Vérifie si un email existe déjà (hors l'employé en cours de modification)
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.email = :email')
            ->setParameter('email', $email);

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Compte les employés d'un agriculteur
     */
    public function countByAgriculteur(int $idAgriculteur): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.idAgriculteur = :id')
            ->setParameter('id', $idAgriculteur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère un employé par son QR code unique
     */
    public function findByQrCode(string $qrCode): ?Employe
    {
        return $this->findOneBy(['qrCodeUnique' => $qrCode]);
    }

    /**
     * Compte les employés par poste — pour les statistiques
     *
     * @return array<string, int>
     */
    public function countByPoste(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('e.poste, COUNT(e.id) as total')
            ->where('e.idAgriculteur = :id')
            ->setParameter('id', $idAgriculteur)
            ->groupBy('e.poste')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $r) {
            $counts[$r['poste'] ?? 'Non défini'] = (int)$r['total'];
        }
        return $counts;
    }

    /**
     * Compte les employés actifs/inactifs — pour les statistiques
     *
     * @return array{actifs: int, inactifs: int}
     */
    public function countByActif(int $idAgriculteur): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('e.actif, COUNT(e.id) as total')
            ->where('e.idAgriculteur = :id')
            ->setParameter('id', $idAgriculteur)
            ->groupBy('e.actif')
            ->getQuery()
            ->getResult();

        $counts = ['actifs' => 0, 'inactifs' => 0];
        foreach ($results as $r) {
            if ($r['actif']) {
                $counts['actifs'] = (int)$r['total'];
            } else {
                $counts['inactifs'] = (int)$r['total'];
            }
        }
        return $counts;
    }
}
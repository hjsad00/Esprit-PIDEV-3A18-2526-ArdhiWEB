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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employe::class);
    }

    /**
     * Tous les employés d'un agriculteur — multi-tenant
     */
    public function findByAgriculteur(int $idAgriculteur): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.idAgriculteur = :id')
            ->setParameter('id', $idAgriculteur)
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Uniquement les employés ACTIFS d'un agriculteur
     * Utilisé par le moteur IA (scoring, chatbot)
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
     * Recherche par nom / prénom / email / poste dans le contexte agriculteur
     */
    public function search(string $terme, int $idAgriculteur): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.idAgriculteur = :id')
            ->andWhere(
                'e.nom LIKE :terme OR e.prenom LIKE :terme OR e.email LIKE :terme OR e.poste LIKE :terme'
            )
            ->setParameter('id', $idAgriculteur)
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
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
}
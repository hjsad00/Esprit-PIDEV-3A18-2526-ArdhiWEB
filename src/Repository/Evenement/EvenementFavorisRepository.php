<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\EvenementFavoris;
use App\Entity\UserAndDiag\User;
use App\Entity\Evenement\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvenementFavorisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvenementFavoris::class);
    }

    public function findByUserAndEvenement(User $user, Evenement $evenement): ?EvenementFavoris
    {
        return $this->findOneBy(['utilisateur' => $user, 'evenement' => $evenement]);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('f.dateAjout', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

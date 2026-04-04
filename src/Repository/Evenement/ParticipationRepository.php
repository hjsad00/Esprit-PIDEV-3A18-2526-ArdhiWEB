<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\Participation;
use App\Entity\UserAndDiag\User;
use App\Entity\Evenement\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function findByUserAndEvenement(User $user, Evenement $evenement): ?Participation
    {
        return $this->findOneBy([
            'utilisateur' => $user,
            'evenement'   => $evenement,
        ]);
    }

    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.utilisateur = :user')
            ->andWhere('p.statut != :annule')
            ->setParameter('user', $user)
            ->setParameter('annule', 'ANNULE')
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForCreator(User $creator): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.evenement', 'e')
            ->where('e.createur = :creator')
            ->setParameter('creator', $creator)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

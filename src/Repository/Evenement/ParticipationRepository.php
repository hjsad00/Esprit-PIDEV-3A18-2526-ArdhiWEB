<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\Participation;
use App\Entity\UserAndDiag\User;
use App\Entity\Evenement\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function findByUserAndEvenement(User $user, Evenement $evenement): ?Participation
    {
        return $this->findOneBy(['utilisateur' => $user, 'evenement' => $evenement]);
    }

    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()->getResult();
    }

    public function findForCreator(User $creator): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.evenement', 'e')
            ->where('e.createur = :creator')
            ->setParameter('creator', $creator)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()->getResult();
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()->getResult();
    }

    public function findReminderCandidates(int $daysBefore): array
    {
        $targetDate = new \DateTime((new \DateTimeImmutable('today'))->modify(sprintf('+%d day', $daysBefore))->format('Y-m-d'));
        $reminderField = $daysBefore === 3 ? 'p.rappelJ3Envoye' : 'p.rappelJ1Envoye';

        return $this->createQueryBuilder('p')
            ->join('p.evenement', 'e')
            ->join('p.utilisateur', 'u')
            ->where('p.statut = :pStatut')
            ->andWhere('e.statut = :eStatut')
            ->andWhere('e.dateDebut = :targetDate')
            ->andWhere(sprintf('%s = false', $reminderField))
            ->setParameter('pStatut', 'CONFIRME')
            ->setParameter('eStatut', 'A_VENIR')
            ->setParameter('targetDate', $targetDate, \Doctrine\DBAL\Types\Types::DATE_MUTABLE)
            ->getQuery()
            ->getResult();
    }

    public function findCertificateCandidates(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.evenement', 'e')
            ->join('p.utilisateur', 'u')
            ->where('p.statut = :pStatut')
            ->andWhere('e.statut = :eStatut')
            ->andWhere('p.attestationEnvoyee = false')
            ->setParameter('pStatut', 'PRESENT')
            ->setParameter('eStatut', 'TERMINE')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts participations for a given event that have a specific statut.
     * Used by ParticipationPredictionService for historical attendance figures.
     */
    public function countByStatut(
        \App\Entity\Evenement\Evenement $evenement,
        string $statut
    ): int {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.evenement = :evenement')
            ->andWhere('p.statut = :statut')
            ->setParameter('evenement', $evenement)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

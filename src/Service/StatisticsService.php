<?php

namespace App\Service;

use App\Entity\UserAndDiag\User;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\ParticipationRepository;
use App\Repository\Evenement\EvenementFavorisRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsService
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private ParticipationRepository $participationRepository,
        private EvenementFavorisRepository $favorisRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get global statistics (for ADMIN role)
     */
    public function getGlobalStatistics(): array
    {
        // Total events
        $totalEvents = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from('App\Entity\Evenement\Evenement', 'e')
            ->getQuery()
            ->getSingleScalarResult();

        // Events by status
        $eventsByStatus = $this->entityManager->createQueryBuilder()
            ->select('e.statut, COUNT(e.id) as count')
            ->from('App\Entity\Evenement\Evenement', 'e')
            ->groupBy('e.statut')
            ->getQuery()
            ->getResult();

        // Events by type
        $eventsByType = $this->entityManager->createQueryBuilder()
            ->select('e.type, COUNT(e.id) as count')
            ->from('App\Entity\Evenement\Evenement', 'e')
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();

        // Total participations
        $totalParticipations = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->getQuery()
            ->getSingleScalarResult();

        // Participations by status
        $participationsByStatus = $this->entityManager->createQueryBuilder()
            ->select('p.statut, COUNT(p.id) as count')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->groupBy('p.statut')
            ->getQuery()
            ->getResult();

        // Top 5 most attended events
        $topEvents = $this->entityManager->createQueryBuilder()
            ->select('e.id, e.titre, COUNT(p.id) as participantCount')
            ->from('App\Entity\Evenement\Evenement', 'e')
            ->leftJoin('App\Entity\Evenement\Participation', 'p', 'WITH', 'p.evenement = e.id')
            ->groupBy('e.id')
            ->orderBy('participantCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return [
            'totalEvents' => $totalEvents,
            'eventsByStatus' => $eventsByStatus,
            'eventsByType' => $eventsByType,
            'totalParticipations' => $totalParticipations,
            'participationsByStatus' => $participationsByStatus,
            'topEvents' => $topEvents,
        ];
    }

    /**
     * Get statistics for a specific user (CLIENT/AGRICULTEUR)
     */
    public function getUserStatistics(User $user): array
    {
        // User's total inscriptions
        $totalInscriptions = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // User's confirmations
        $confirmations = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->where('p.utilisateur = :user')
            ->andWhere('p.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'CONFIRME')
            ->getQuery()
            ->getSingleScalarResult();

        // User's favorites
        $totalFavorites = $this->entityManager->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from('App\Entity\Evenement\EvenementFavoris', 'f')
            ->where('f.utilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // User's inscriptions by status
        $inscriptionsByStatus = $this->entityManager->createQueryBuilder()
            ->select('p.statut, COUNT(p.id) as count')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->where('p.utilisateur = :user')
            ->groupBy('p.statut')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // User's created events (if AGRICULTEUR)
        $createdEvents = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from('App\Entity\Evenement\Evenement', 'e')
            ->where('e.createur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalInscriptions' => $totalInscriptions,
            'confirmations' => $confirmations,
            'totalFavorites' => $totalFavorites,
            'inscriptionsByStatus' => $inscriptionsByStatus,
            'createdEvents' => $createdEvents,
        ];
    }

    /**
     * Get statistics for creator's events
     */
    public function getCreatorStatistics(User $creator): array
    {
        // Creator's total events
        $totalEvents = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from('App\Entity\Evenement\Evenement', 'e')
            ->where('e.createur = :creator')
            ->setParameter('creator', $creator)
            ->getQuery()
            ->getSingleScalarResult();

        // Creator's total participants
        $totalParticipants = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->join('p.evenement', 'e')
            ->where('e.createur = :creator')
            ->setParameter('creator', $creator)
            ->getQuery()
            ->getSingleScalarResult();

        // Creator's participants by status
        $participantsByStatus = $this->entityManager->createQueryBuilder()
            ->select('p.statut, COUNT(p.id) as count')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->join('p.evenement', 'e')
            ->where('e.createur = :creator')
            ->groupBy('p.statut')
            ->setParameter('creator', $creator)
            ->getQuery()
            ->getResult();

        // Average rating for events
        $avgRating = $this->entityManager->createQueryBuilder()
            ->select('AVG(p.avis) as average')
            ->from('App\Entity\Evenement\Participation', 'p')
            ->join('p.evenement', 'e')
            ->where('e.createur = :creator')
            ->andWhere('p.avis IS NOT NULL')
            ->setParameter('creator', $creator)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalEvents' => $totalEvents,
            'totalParticipants' => $totalParticipants,
            'participantsByStatus' => $participantsByStatus,
            'avgRating' => $avgRating ? round($avgRating, 2) : 0,
        ];
    }

    /**
     * Get top-rated events
     */
    public function getTopRatedEvents(int $limit = 5): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        return $qb->select('e.id, e.titre, AVG(p.avis) as avgRating, COUNT(p.id) as ratingCount')
            ->from('App\Entity\Evenement\Evenement', 'e')
            ->leftJoin('App\Entity\Evenement\Participation', 'p', 'WITH', 'p.evenement = e.id AND p.avis IS NOT NULL')
            ->groupBy('e.id')
            ->having('COUNT(p.id) > 0')
            ->orderBy('avgRating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

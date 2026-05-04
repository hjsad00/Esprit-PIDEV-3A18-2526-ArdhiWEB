<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use App\Entity\UserAndDiag\User;
use App\Repository\Evenement\EvenementFavorisRepository;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\ParticipationRepository;

class StatisticsService
{
    public function __construct(
        private EvenementRepository $evenementRepo,
        private ParticipationRepository $participationRepo,
        private EvenementFavorisRepository $favorisRepo
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getGlobalStatistics(): array
    {
        $byStatus = $this->evenementRepo->createQueryBuilder('e')
            ->select('e.statut AS statut, COUNT(e.id) AS count')
            ->groupBy('e.statut')
            ->getQuery()
            ->getResult();

        $byType = $this->evenementRepo->createQueryBuilder('e')
            ->select('e.type AS type, COUNT(e.id) AS count')
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();

        $partByStatus = $this->participationRepo->createQueryBuilder('p')
            ->select('p.statut AS statut, COUNT(p.id) AS count')
            ->groupBy('p.statut')
            ->getQuery()
            ->getResult();

        $totalEvents = (int) $this->evenementRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalParticipations = (int) $this->participationRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $avgRating = $this->participationRepo->createQueryBuilder('p')
            ->select('AVG(p.note)')
            ->where('p.note > 0')
            ->getQuery()
            ->getSingleScalarResult();

        $topEvents = $this->evenementRepo->createQueryBuilder('e')
            ->select('e.id, e.titre, e.type, COUNT(p.id) AS participantCount')
            ->leftJoin('e.participations', 'p', 'WITH', "p.statut IN ('CONFIRME','PRESENT')")
            ->groupBy('e.id')
            ->orderBy('participantCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $topRatedRows = $this->participationRepo->createQueryBuilder('p')
            ->select('IDENTITY(p.evenement) AS id, AVG(p.note) AS avgRating, COUNT(p.id) AS cnt')
            ->where('p.note > 0')
            ->groupBy('p.evenement')
            ->orderBy('avgRating', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $topRatedEvents = [];
        foreach ($topRatedRows as $row) {
            $eventId = isset($row['id']) ? (int) $row['id'] : 0;
            $event = $this->evenementRepo->find($eventId);
            if ($event instanceof Evenement) {
                $topRatedEvents[] = [
                    'titre' => $event->getTitre(),
                    'type' => $event->getType(),
                    'avgRating' => round((float) $row['avgRating'], 1),
                    'count' => (int) $row['cnt'],
                ];
            }
        }

        $presents = 0;
        $confirmes = 0;
        $annules = 0;

        foreach ($partByStatus as $row) {
            $statut = $row['statut'] ?? null;
            $count = isset($row['count']) ? (int) $row['count'] : 0;

            if ($statut === 'PRESENT') {
                $presents += $count;
            }

            if (in_array($statut, ['CONFIRME', 'PRESENT'], true)) {
                $confirmes += $count;
            }

            if ($statut === 'ANNULE') {
                $annules += $count;
            }
        }

        $tauxPresence = $confirmes > 0 ? round($presents * 100 / $confirmes, 1) : 0.0;
        $tauxAnnulation = $totalParticipations > 0 ? round($annules * 100 / $totalParticipations, 1) : 0.0;

        return [
            'totalEvents' => $totalEvents,
            'totalParticipations' => $totalParticipations,
            'eventsByStatus' => $byStatus,
            'eventsByType' => $byType,
            'participationsByStatus' => $partByStatus,
            'tauxPresence' => $tauxPresence,
            'tauxAnnulation' => $tauxAnnulation,
            'avgRating' => $avgRating ? round((float) $avgRating, 1) : 0.0,
            'topEvents' => $topEvents,
            'topRatedEvents' => $topRatedEvents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserStatistics(User $user): array
    {
        /** @var list<Participation> $participations */
        $participations = $this->participationRepo->findByUserOrdered($user);

        $statusMap = [];
        foreach ($participations as $participation) {
            $status = $participation->getStatut();
            $statusMap[$status] = ($statusMap[$status] ?? 0) + 1;
        }

        $byStatus = [];
        foreach ($statusMap as $status => $count) {
            $byStatus[] = ['statut' => $status, 'count' => $count];
        }

        $createdEvents = (int) $this->evenementRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.createur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalInscriptions' => count($participations),
            'confirmations' => count(array_filter(
                $participations,
                static fn (Participation $participation): bool => in_array($participation->getStatut(), ['CONFIRME', 'PRESENT'], true)
            )),
            'totalFavorites' => (int) $this->favorisRepo->count(['utilisateur' => $user]),
            'createdEvents' => $createdEvents,
            'inscriptionsByStatus' => $byStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreatorStatistics(User $user): array
    {
        /** @var list<Participation> $participations */
        $participations = $this->participationRepo->findForCreator($user);

        $statusMap = [];
        foreach ($participations as $participation) {
            $status = $participation->getStatut();
            $statusMap[$status] = ($statusMap[$status] ?? 0) + 1;
        }

        $byStatus = [];
        foreach ($statusMap as $status => $count) {
            $byStatus[] = ['statut' => $status, 'count' => $count];
        }

        $rated = array_filter(
            $participations,
            static fn (Participation $participation): bool => $participation->getNote() > 0
        );

        return [
            'totalParticipants' => count($participations),
            'presents' => count(array_filter(
                $participations,
                static fn (Participation $participation): bool => $participation->getStatut() === 'PRESENT'
            )),
            'avgRating' => count($rated) > 0
                ? round(array_sum(array_map(static fn (Participation $participation): int => $participation->getNote(), $rated)) / count($rated), 1)
                : 0.0,
            'participantsByStatus' => $byStatus,
        ];
    }
}

<?php

namespace App\Service\Evenement;

use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\ParticipationRepository;

class StatisticsService
{
    public function __construct(
        private EvenementRepository         $evenementRepo,
        private ParticipationRepository      $participationRepo,
        private \App\Repository\Evenement\EvenementFavorisRepository $favorisRepo
    ) {}

    /**
     * Full global stats — mirrors JavaFX getStatistiquesGlobales()
     */
    public function getGlobalStatistics(): array
    {
        // Events by status (raw DQL)
        $byStatus = $this->evenementRepo->createQueryBuilder('e')
            ->select('e.statut AS statut, COUNT(e.id) AS count')
            ->groupBy('e.statut')
            ->getQuery()->getResult();

        // Events by type
        $byType = $this->evenementRepo->createQueryBuilder('e')
            ->select('e.type AS type, COUNT(e.id) AS count')
            ->groupBy('e.type')
            ->getQuery()->getResult();

        // Participations by status
        $partByStatus = $this->participationRepo->createQueryBuilder('p')
            ->select('p.statut AS statut, COUNT(p.id) AS count')
            ->groupBy('p.statut')
            ->getQuery()->getResult();

        // Total events & participations
        $totalEvents        = (int) $this->evenementRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
        $totalParticipations = (int) $this->participationRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Taux présence
        $presents  = 0; $confirmes = 0;
        foreach ($partByStatus as $row) {
            if ($row['statut'] === 'PRESENT')  { $presents  += $row['count']; }
            if (in_array($row['statut'], ['CONFIRME', 'PRESENT'])) { $confirmes += $row['count']; }
        }
        $tauxPresence   = $confirmes > 0 ? round($presents * 100 / $confirmes, 1) : 0;

        // Taux annulation
        $annules       = 0;
        foreach ($partByStatus as $row) {
            if ($row['statut'] === 'ANNULE') { $annules += $row['count']; }
        }
        $tauxAnnulation = $totalParticipations > 0 ? round($annules * 100 / $totalParticipations, 1) : 0;

        // Global average rating
        $avgRating = $this->participationRepo->createQueryBuilder('p')
            ->select('AVG(p.note)')
            ->where('p.note > 0')
            ->getQuery()->getSingleScalarResult();
        $avgRating = $avgRating ? round((float)$avgRating, 1) : 0;

        // Top 5 events by participants
        $topEvents = $this->evenementRepo->createQueryBuilder('e')
            ->select('e.id, e.titre, e.type, COUNT(p.id) AS participantCount')
            ->leftJoin('e.participations', 'p', 'WITH', "p.statut IN ('CONFIRME','PRESENT')")
            ->groupBy('e.id')
            ->orderBy('participantCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery()->getResult();

        // Top rated events (mirrors getTopRatedEvents)
        $topRated = $this->participationRepo->createQueryBuilder('p')
            ->select('IDENTITY(p.evenement) AS id, AVG(p.note) AS avgRating, COUNT(p.id) AS cnt')
            ->where('p.note > 0')
            ->groupBy('p.evenement')
            ->orderBy('avgRating', 'DESC')
            ->setMaxResults(5)
            ->getQuery()->getResult();

        // Enrich topRated with event titles
        $topRatedEvents = [];
        foreach ($topRated as $row) {
            $ev = $this->evenementRepo->find($row['id']);
            if ($ev) {
                $topRatedEvents[] = [
                    'titre'      => $ev->getTitre(),
                    'type'       => $ev->getType(),
                    'avgRating'  => round((float)$row['avgRating'], 1),
                    'count'      => $row['cnt'],
                ];
            }
        }

        return [
            'totalEvents'            => $totalEvents,
            'totalParticipations'    => $totalParticipations,
            'eventsByStatus'         => $byStatus,
            'eventsByType'           => $byType,
            'participationsByStatus' => $partByStatus,
            'tauxPresence'           => $tauxPresence,
            'tauxAnnulation'         => $tauxAnnulation,
            'avgRating'              => $avgRating,
            'topEvents'              => $topEvents,
            'topRatedEvents'         => $topRatedEvents,
        ];
    }

    /**
     * Per-user stats — mirrors JavaFX user statistics
     */
    public function getUserStatistics($user): array
    {
        $allPart = $this->participationRepo->findByUserOrdered($user);

        $total        = count($allPart);
        $confirmes    = count(array_filter($allPart, fn($p) => in_array($p->getStatut(), ['CONFIRME','PRESENT'])));
        $favorites    = $this->favorisRepo->count(['utilisateur' => $user]);

        // By status
        $statusMap = [];
        foreach ($allPart as $p) {
            $statusMap[$p->getStatut()] = ($statusMap[$p->getStatut()] ?? 0) + 1;
        }
        $byStatus = [];
        foreach ($statusMap as $s => $c) { $byStatus[] = ['statut' => $s, 'count' => $c]; }

        // Events created by user
        $created = (int) $this->evenementRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.createur = :u')
            ->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();

        return [
            'totalInscriptions'   => $total,
            'confirmations'       => $confirmes,
            'totalFavorites'      => (int)$favorites,
            'inscriptionsByStatus'=> $byStatus,
            'createdEvents'       => $created,
        ];
    }

    /**
     * Creator/organizer stats — mirrors JavaFX creator statistics
     */
    public function getCreatorStatistics($user): array
    {
        $parts = $this->participationRepo->findForCreator($user);

        $total = count($parts);
        $presents = count(array_filter($parts, fn($p) => $p->getStatut() === 'PRESENT'));

        $notes   = array_filter($parts, fn($p) => $p->getNote() > 0);
        $avgNote = count($notes) > 0
            ? round(array_sum(array_map(fn($p) => $p->getNote(), $notes)) / count($notes), 1)
            : 0;

        $statusMap = [];
        foreach ($parts as $p) {
            $statusMap[$p->getStatut()] = ($statusMap[$p->getStatut()] ?? 0) + 1;
        }
        $byStatus = [];
        foreach ($statusMap as $s => $c) { $byStatus[] = ['statut' => $s, 'count' => $c]; }

        return [
            'totalParticipants'    => $total,
            'presents'             => $presents,
            'avgRating'            => $avgNote,
            'participantsByStatus' => $byStatus,
        ];
    }
}

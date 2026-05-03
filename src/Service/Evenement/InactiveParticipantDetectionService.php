<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Participation;
use App\Repository\Evenement\ParticipationRepository;

/**
 * Detects inactive / at-risk participants and computes risk scores.
 * Ported from the Java InactiveParticipantDetectionService.
 */
class InactiveParticipantDetectionService
{
    public function __construct(
        private ParticipationRepository $participationRepo
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Analyse every participant and return risk profiles sorted by score desc.
     *
     * @param \App\Entity\UserAndDiag\User|null $creator Filter by event creator (for Agriculteurs)
     * @return list<array<string, mixed>>
     */
    public function detecterParticipantsInactifs(?\App\Entity\UserAndDiag\User $creator = null): array
    {
        /** @var list<Participation> $all */
        $all = $creator
            ? $this->participationRepo->findForCreator($creator)
            : $this->participationRepo->findAll();

        // Group by user id
        $byUser = [];
        foreach ($all as $p) {
            $user = $p->getUtilisateur();
            $uid = $user?->getId();
            if ($uid === null) continue;
            $byUser[$uid][] = $p;
        }

        $profiles = [];
        foreach ($byUser as $userId => $participations) {
            $profiles[] = $this->analyserUtilisateur($userId, $participations);
        }

        // Sort by risk score descending
        usort($profiles, fn($a, $b) => $b['riskScore'] <=> $a['riskScore']);

        return $profiles;
    }

    /**
     * Returns global inactivity statistics over the given profiles.
     *
     * @param list<array<string, mixed>> $profiles
     * @return array<string, float|int>
     */
    public function genererStatistiques(array $profiles): array
    {
        $total     = count($profiles);
        $urgent    = count(array_filter($profiles, fn($p) => $p['riskScore'] >= 80));
        $important = count(array_filter($profiles, fn($p) => $p['riskScore'] >= 60 && $p['riskScore'] < 80));
        $modere    = count(array_filter($profiles, fn($p) => $p['riskScore'] >= 40 && $p['riskScore'] < 60));

        $scoreMoyen = $total > 0
            ? array_sum(array_column($profiles, 'riskScore')) / $total
            : 0.0;

        return compact('total', 'urgent', 'important', 'modere', 'scoreMoyen');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE SCORING
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @param Participation[] $participations
     * @return array<string, mixed>
     */
    private function analyserUtilisateur(int $userId, array $participations): array
    {
        $now        = new \DateTime();
        $riskScore  = 0.0;
        $indicateurs = [];

        $user = $participations[0]->getUtilisateur();

        // ── 1. Cancellation rate ─────────────────────────────────────────
        $annulations    = count(array_filter($participations, fn($p) => $p->getStatut() === 'ANNULE'));
        $totalCount     = count($participations);
        $tauxAnnulation = ($totalCount > 0) ? ($annulations / $totalCount * 100) : 0;

        if ($tauxAnnulation > 50) {
            $riskScore += 30;
            $indicateurs[] = sprintf("Taux d'annulation très élevé: %.1f%%", $tauxAnnulation);
        } elseif ($tauxAnnulation > 30) {
            $riskScore += 20;
            $indicateurs[] = sprintf("Taux d'annulation élevé: %.1f%%", $tauxAnnulation);
        }

        // ── 2. Absence rate (confirmed but never present) ────────────────
        $confirmes = count(array_filter($participations, fn($p) => $p->getStatut() === 'CONFIRME'));
        $presents  = count(array_filter($participations, fn($p) => $p->getStatut() === 'PRESENT'));

        if ($confirmes > 0) {
            $tauxAbsence = ($confirmes - $presents) / $confirmes * 100;
            if ($tauxAbsence > 60) {
                $riskScore += 25;
                $indicateurs[] = sprintf("Taux d'absence très élevé: %.1f%%", $tauxAbsence);
            } elseif ($tauxAbsence > 40) {
                $riskScore += 15;
                $indicateurs[] = sprintf("Taux d'absence élevé: %.1f%%", $tauxAbsence);
            }
        }

        // ── 3. Days since last activity ──────────────────────────────────
        $derniereDate = null;
        foreach ($participations as $p) {
            $d = $p->getDateInscription();
            if ($d && (!$derniereDate || $d > $derniereDate)) {
                $derniereDate = $d;
            }
        }

        $joursSansActivite = $derniereDate
            ? (int) $now->diff($derniereDate)->days
            : 365;

        if ($joursSansActivite > 180) {
            $riskScore += 20;
            $indicateurs[] = "Inactif depuis {$joursSansActivite} jours";
        } elseif ($joursSansActivite > 90) {
            $riskScore += 10;
            $indicateurs[] = "Peu actif (dernière activité il y a {$joursSansActivite} jours)";
        }

        // ── 4. Pending (EN_ATTENTE) > 7 days ────────────────────────────
        $enAttente = count(array_filter($participations, function ($p) use ($now) {
            if ($p->getStatut() !== 'EN_ATTENTE') return false;
            $d = $p->getDateInscription();
            return $d && $now->diff($d)->days > 7;
        }));

        if ($enAttente > 0) {
            $riskScore += 15;
            $indicateurs[] = "{$enAttente} inscription(s) en attente non confirmée(s)";
        }

        // ── 5. New user, barely engaged ──────────────────────────────────
        if (count($participations) === 1 && $joursSansActivite > 30) {
            $riskScore += 10;
            $indicateurs[] = 'Nouvel utilisateur peu engagé';
        }

        $riskScore = min(100, $riskScore);

        return [
            'userId'             => $userId,
            'nom'                => $user?->getNom(),
            'prenom'             => $user?->getPrenom(),
            'email'              => $user?->getEmail(),
            'nomComplet'         => trim(($user?->getPrenom() ?? '') . ' ' . ($user?->getNom() ?? '')),
            'riskScore'          => $riskScore,
            'riskLevel'          => $this->riskLevel($riskScore),
            'riskColor'          => $this->riskColor($riskScore),
            'totalParticipations'=> count($participations),
            'annulations'        => $annulations,
            'presences'          => $presents,
            'joursSansActivite'  => $joursSansActivite,
            'indicateurs'        => $indicateurs,
            'recommandation'     => $this->genererRecommandation($riskScore),
        ];
    }

    private function genererRecommandation(float $score): string
    {
        if ($score >= 80) return '🔴 URGENT: Relance immédiate avec offre spéciale personnalisée';
        if ($score >= 60) return '🟠 IMPORTANT: Relance avec questionnaire de satisfaction';
        if ($score >= 40) return '🟡 ATTENTION: Email de réengagement avec recommandations';
        return '🟢 Monitoring: Surveiller l\'évolution';
    }

    private function riskLevel(float $score): string
    {
        if ($score >= 80) return 'URGENT';
        if ($score >= 60) return 'IMPORTANT';
        if ($score >= 40) return 'MODÉRÉ';
        return 'FAIBLE';
    }

    private function riskColor(float $score): string
    {
        if ($score >= 80) return '#E74C3C';
        if ($score >= 60) return '#F39C12';
        if ($score >= 40) return '#F1C40F';
        return '#27AE60';
    }
}

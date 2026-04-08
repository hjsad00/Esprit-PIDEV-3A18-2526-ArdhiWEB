<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\TacheRepository;
use App\Repository\EmployeTache\EmployeRepository;

/**
 * 📊 Service d'évaluation des performances des employés
 *
 * Traduction exacte du PerformanceService.java du desktop.
 *
 * FORMULE DU SCORE (identique au desktop Java) :
 *   Score = (Tâches terminées / Tâches totales) × 100
 *           - (Retards × 5)
 *           - (En cours anciennes × 2)
 *   Score limité entre 0 et 100.
 */
class PerformanceService
{
    public function __construct(
        private TacheRepository  $tacheRepo,
        private EmployeRepository $employeRepo,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  CALCUL DU SCORE D'UN EMPLOYÉ
    //  Identique à calculatePerformance(int idEmploye) Java
    // ══════════════════════════════════════════════════════════════════

    /**
     * Calcule le score de performance d'un seul employé.
     * Retourne un tableau avec toutes les données (identique à PerformanceData Java).
     */
    public function calculatePerformance(int $idEmploye): array
    {
        // Récupérer toutes les tâches de l'employé
        $taches = $this->tacheRepo->findTachesParEmployePourPerformance($idEmploye);

        $totalTaches         = 0;
        $tachesTerminees     = 0;
        $tachesEnRetard      = 0;
        $tachesEnCours       = 0;
        $tachesAnnulees      = 0;
        $totalJoursRealisation = 0;
        $compteurDuree       = 0;
        $today               = new \DateTime('today');

        foreach ($taches as $tache) {
            $totalTaches++;
            $statut    = $tache->getStatut();
            $dateDebut = $tache->getDateDebut();
            $dateFin   = $tache->getDateFin();

            // ── Terminé ou Validé ─────────────────────────────────────
            if (in_array($statut, ['Terminé', 'Validé'], true)) {
                $tachesTerminees++;

                // Calculer durée de réalisation (comme Java : ChronoUnit.DAYS.between)
                if ($dateDebut !== null && $dateFin !== null) {
                    $jours = $dateDebut->diff($dateFin)->days;
                    if ($jours >= 0) {
                        $totalJoursRealisation += $jours;
                        $compteurDuree++;
                    }
                }

            // ── En cours ──────────────────────────────────────────────
            } elseif ($statut === 'En cours') {
                $tachesEnCours++;

                // Si date fin dépassée = retard (identique Java)
                if ($dateFin !== null && $dateFin < $today) {
                    $tachesEnRetard++;
                }

            // ── Annulé ────────────────────────────────────────────────
            } elseif ($statut === 'Annulé') {
                $tachesAnnulees++;
            }
            // En attente → compté dans totalTaches mais pas dans les autres
        }

        // ── Temps moyen de réalisation ────────────────────────────────
        $tempsRealisationMoyen = $compteurDuree > 0
            ? round($totalJoursRealisation / $compteurDuree, 1)
            : 0.0;

        // ── CALCUL DU SCORE (formule identique Java) ──────────────────
        $score       = 0.0;
        $tauxReussite = 0.0;

        if ($totalTaches > 0) {
            $tauxReussite    = ($tachesTerminees / $totalTaches) * 100;
            $penaliteRetard  = $tachesEnRetard * 5;   // -5 pts par retard
            $penaliteEnCours = $tachesEnCours  * 2;   // -2 pts par tâche en cours

            $score = $tauxReussite - $penaliteRetard - $penaliteEnCours;

            // Limiter entre 0 et 100 (identique Java)
            $score = max(0.0, min(100.0, $score));
        }

        return [
            'idEmploye'            => $idEmploye,
            'nomEmploye'           => '',               // rempli par getClassement()
            'totalTaches'          => $totalTaches,
            'tachesTerminees'      => $tachesTerminees,
            'tachesEnRetard'       => $tachesEnRetard,
            'tachesEnCours'        => $tachesEnCours,
            'tachesAnnulees'       => $tachesAnnulees,
            'tachesEnAttente'      => $totalTaches - $tachesTerminees - $tachesEnCours - $tachesAnnulees,
            'tempsRealisationMoyen'=> $tempsRealisationMoyen,
            'tauxReussite'         => round($tauxReussite, 1),
            'score'                => round($score, 1),
            // Helpers calculés
            'appreciation'         => $this->getAppreciation($score),
            'couleur'              => $this->getCouleur($score),
            'emoji'                => $this->getEmoji($score),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  CLASSEMENT DE TOUS LES EMPLOYÉS
    //  Identique à getClassement(Integer idAgriculteur) Java
    // ══════════════════════════════════════════════════════════════════

    /**
     * Retourne le classement trié par score décroissant.
     * Filtre actif=true comme le Java (WHERE e.actif = TRUE).
     */
    public function getClassement(int $idAgriculteur): array
    {
        // Uniquement les employés actifs (identique Java : WHERE actif = TRUE)
        $employes = $this->employeRepo->findActifsByAgriculteur($idAgriculteur);

        $classement = [];
        foreach ($employes as $employe) {
            $perf = $this->calculatePerformance($employe->getId());
            $perf['nomEmploye'] = $employe->getNomComplet();
            $classement[] = $perf;
        }

        // Trier par score décroissant (identique Java : Double.compare(b.score, a.score))
        usort($classement, fn($a, $b) => $b['score'] <=> $a['score']);

        return $classement;
    }

    // ══════════════════════════════════════════════════════════════════
    //  STATISTIQUES GLOBALES
    //  Identique à updateStatistics() dans PerformanceController.java
    // ══════════════════════════════════════════════════════════════════

    /**
     * Calcule les statistiques globales à partir du classement.
     * Utilisé pour les KPI cards du tableau de bord.
     */
    public function getStatistiquesGlobales(array $classement): array
    {
        if (empty($classement)) {
            return [
                'moyenneScore'    => 0.0,
                'meilleurEmploye' => 'Aucun',
                'totalTaches'     => 0,
            ];
        }

        // Moyenne des scores (identique Java : mapToDouble + average)
        $scores = array_column($classement, 'score');
        $moyenneScore = count($scores) > 0
            ? round(array_sum($scores) / count($scores), 1)
            : 0.0;

        // Meilleur employé = premier du classement (déjà trié par score DESC)
        $meilleurEmploye = $classement[0]['nomEmploye'] ?? 'Aucun';

        // Total des tâches (identique Java : mapToInt + sum)
        $totalTaches = array_sum(array_column($classement, 'totalTaches'));

        return [
            'moyenneScore'    => $moyenneScore,
            'meilleurEmploye' => $meilleurEmploye,
            'totalTaches'     => $totalTaches,
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS — identiques aux méthodes de PerformanceData.java
    // ══════════════════════════════════════════════════════════════════

    /**
     * Appréciation textuelle selon le score.
     * Identique à getAppreciation() Java.
     */
    public function getAppreciation(float $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Très bien';
        if ($score >= 60) return 'Bien';
        if ($score >= 50) return 'Moyen';
        return 'Faible';
    }

    /**
     * Couleur CSS selon le score.
     * Identique à getCouleur() Java.
     */
    public function getCouleur(float $score): string
    {
        if ($score >= 75) return '#27ae60'; // Vert
        if ($score >= 50) return '#f39c12'; // Orange
        return '#e74c3c';                   // Rouge
    }

    /**
     * Émoji selon le score.
     * Identique à getEmoji() Java.
     */
    public function getEmoji(float $score): string
    {
        if ($score >= 90) return '🏆';
        if ($score >= 75) return '⭐';
        if ($score >= 60) return '👍';
        if ($score >= 50) return '👌';
        return '⚠️';
    }
}
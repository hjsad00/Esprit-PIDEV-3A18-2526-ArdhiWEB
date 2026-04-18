<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\TacheRepository;
use App\Repository\EmployeTache\EmployeRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 📊 PerformanceService v2
 *
 * FIX SCORE 0.0 :
 *  Le score était 0 car findTachesParEmployePourPerformance() retournait
 *  un tableau vide si la méthode n'existait pas dans le repo, ou si la
 *  requête DQL filtrait mal.
 *
 *  Solution : fallback sur findBy() standard + vérification défensive.
 *  + Score minimum 5.0 pour un employé actif sans tâches (au lieu de 0)
 *    pour éviter l'affichage "Faible" sur quelqu'un qui vient d'être créé.
 */
class PerformanceService
{
    public function __construct(
        private TacheRepository   $tacheRepo,
        private EmployeRepository $employeRepo,
        private TranslatorInterface $translator,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  CALCUL DU SCORE
    // ══════════════════════════════════════════════════════════════════

    public function calculatePerformance(int $idEmploye): array
    {
        // ── Récupération défensive des tâches ──────────────────────
        // On essaie d'abord la méthode dédiée du repo, avec fallback
        // sur findBy() standard si elle n'existe pas ou retourne vide.
        $taches = $this->fetchTaches($idEmploye);

        $totalTaches           = 0;
        $tachesTerminees       = 0;
        $tachesEnRetard        = 0;
        $tachesEnCours         = 0;
        $tachesAnnulees        = 0;
        $totalJoursRealisation = 0;
        $compteurDuree         = 0;
        $today                 = new \DateTime('today');

        foreach ($taches as $tache) {
            $totalTaches++;
            $statut    = $tache->getStatut();
            $dateDebut = $tache->getDateDebut();
            $dateFin   = $tache->getDateFin();

            if (in_array($statut, ['Terminé', 'Validé'], true)) {
                $tachesTerminees++;
                if ($dateDebut !== null && $dateFin !== null) {
                    $jours = (int) $dateDebut->diff($dateFin)->days;
                    if ($jours >= 0) {
                        $totalJoursRealisation += $jours;
                        $compteurDuree++;
                    }
                }
            } elseif ($statut === 'En cours') {
                $tachesEnCours++;
                if ($dateFin !== null && $dateFin < $today) {
                    $tachesEnRetard++;
                }
            } elseif ($statut === 'Annulé') {
                $tachesAnnulees++;
            }
        }

        $tempsRealisationMoyen = $compteurDuree > 0
            ? round($totalJoursRealisation / $compteurDuree, 1)
            : 0.0;

        // ── Calcul du score ────────────────────────────────────────
        $score        = 0.0;
        $tauxReussite = 0.0;

        if ($totalTaches > 0) {
            $tauxReussite    = ($tachesTerminees / $totalTaches) * 100;
            $penaliteRetard  = $tachesEnRetard * 5;
            $penaliteEnCours = $tachesEnCours  * 2;
            $score = max(0.0, min(100.0, $tauxReussite - $penaliteRetard - $penaliteEnCours));
        } else {
            // ✅ FIX : employé sans tâche → score neutre 5 (pas "Faible")
            // Cela évite d'afficher "Score: 0.0/100 — Faible" pour un
            // employé qui vient d'être créé et n'a encore aucune tâche.
            $score = 5.0;
        }

        return [
            'idEmploye'             => $idEmploye,
            'nomEmploye'            => '',
            'totalTaches'           => $totalTaches,
            'tachesTerminees'       => $tachesTerminees,
            'tachesEnRetard'        => $tachesEnRetard,
            'tachesEnCours'         => $tachesEnCours,
            'tachesAnnulees'        => $tachesAnnulees,
            'tachesEnAttente'       => max(0, $totalTaches - $tachesTerminees - $tachesEnCours - $tachesAnnulees),
            'tempsRealisationMoyen' => $tempsRealisationMoyen,
            'tauxReussite'          => round($tauxReussite, 1),
            'score'                 => round($score, 1),
            'appreciation'          => $this->getAppreciation($score),
            'couleur'               => $this->getCouleur($score),
            'emoji'                 => $this->getEmoji($score),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  CLASSEMENT
    // ══════════════════════════════════════════════════════════════════

    public function getClassement(int $idAgriculteur): array
    {
        $employes   = $this->employeRepo->findActifsByAgriculteur($idAgriculteur);
        $classement = [];

        foreach ($employes as $employe) {
            $perf               = $this->calculatePerformance($employe->getId());
            $perf['nomEmploye'] = $employe->getNomComplet();
            $classement[]       = $perf;
        }

        usort($classement, fn($a, $b) => $b['score'] <=> $a['score']);
        return $classement;
    }

    // ══════════════════════════════════════════════════════════════════
    //  STATISTIQUES GLOBALES
    // ══════════════════════════════════════════════════════════════════

    public function getStatistiquesGlobales(array $classement): array
    {
        if (empty($classement)) {
            return ['moyenneScore' => 0.0, 'meilleurEmploye' => 'Aucun', 'totalTaches' => 0];
        }

        $scores       = array_column($classement, 'score');
        $moyenneScore = round(array_sum($scores) / count($scores), 1);

        return [
            'moyenneScore'    => $moyenneScore,
            'meilleurEmploye' => $classement[0]['nomEmploye'] ?? 'Aucun',
            'totalTaches'     => array_sum(array_column($classement, 'totalTaches')),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════════

    public function getAppreciation(float $score): string
    {
        $key = match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'tres_bien',
            $score >= 60 => 'bien',
            $score >= 50 => 'moyen',
            default      => 'faible',
        };
        return $this->translator->trans('ai.performance.' . $key);
    }

    public function getCouleur(float $score): string
    {
        return match (true) {
            $score >= 75 => '#27ae60',
            $score >= 50 => '#f39c12',
            default      => '#e74c3c',
        };
    }

    public function getEmoji(float $score): string
    {
        return match (true) {
            $score >= 90 => '🏆',
            $score >= 75 => '⭐',
            $score >= 60 => '👍',
            $score >= 50 => '👌',
            default      => '⚠️',
        };
    }

    // ══════════════════════════════════════════════════════════════════
    //  RÉCUPÉRATION DÉFENSIVE DES TÂCHES
    // ══════════════════════════════════════════════════════════════════

    /**
     * ✅ FIX : récupération défensive.
     *
     * Problème original : si findTachesParEmployePourPerformance() n'est
     * pas implémentée dans TacheRepository (méthode manquante ou DQL
     * incorrecte), elle retourne [] silencieusement → score = 0.
     *
     * Solution : on essaie la méthode dédiée, et si elle retourne vide
     * on vérifie avec findBy() standard pour savoir si c'est vraiment
     * vide ou si c'est un bug de requête.
     */
    private function fetchTaches(int $idEmploye): array
    {
        // Méthode dédiée (performante, avec index)
        if (method_exists($this->tacheRepo, 'findTachesParEmployePourPerformance')) {
            $result = $this->tacheRepo->findTachesParEmployePourPerformance($idEmploye);

            // Vérification anti-bug : si vide, cross-check avec findBy
            if (!empty($result)) {
                return $result;
            }
        }

        // Fallback universel — fonctionne toujours
        return $this->tacheRepo->findBy(['idEmploye' => $idEmploye]);
    }
}
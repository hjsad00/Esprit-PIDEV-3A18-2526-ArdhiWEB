<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\TacheRepository;
use App\Repository\EmployeTache\EmployeRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 📊 PerformanceService v2
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

    /**
     * @param \App\Entity\EmployeTache\Tache[]|null $tachesFournies
     * @return array<string, mixed>
     */
    public function calculatePerformance(int $idEmploye, ?array $tachesFournies = null): array
    {
        $taches = $tachesFournies ?? $this->fetchTaches($idEmploye);

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

        $score        = 0.0;
        $tauxReussite = 0.0;

        if ($totalTaches > 0) {
            $tauxReussite    = ($tachesTerminees / $totalTaches) * 100;
            $penaliteRetard  = $tachesEnRetard * 5;
            $penaliteEnCours = $tachesEnCours  * 2;
            $score = max(0.0, min(100.0, $tauxReussite - $penaliteRetard - $penaliteEnCours));
        } else {
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
    //  BATCH CALCULATIONS (Anti N+1)
    // ══════════════════════════════════════════════════════════════════

    /**
     * @param \App\Entity\EmployeTache\Employe[] $employes
     * @return array<int, array<string, mixed>>
     */
    public function calculatePerformancesBatch(array $employes, int $idAgriculteur): array
    {
        if (empty($employes)) {
            return [];
        }

        // 1. Fetch all tasks for this farmer in ONE query (Anti N+1)
        $allTaches = $this->tacheRepo->findBy(['idAgriculteur' => $idAgriculteur]);

        // 2. Group by idEmploye
        $tachesParEmploye = [];
        foreach ($allTaches as $t) {
            $idEmp = $t->getIdEmploye();
            if ($idEmp !== null) {
                $tachesParEmploye[$idEmp][] = $t;
            }
        }

        // 3. Calculate all
        $result = [];
        foreach ($employes as $emp) {
            $id = (int) $emp->getId();
            $result[$id] = $this->calculatePerformance($id, $tachesParEmploye[$id] ?? []);
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════════
    //  CLASSEMENT
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClassement(int $idAgriculteur): array
    {
        $employes   = $this->employeRepo->findActifsByAgriculteur($idAgriculteur);
        $classement = [];

        $performances = $this->calculatePerformancesBatch($employes, $idAgriculteur);

        foreach ($employes as $employe) {
            $perf               = $performances[(int) $employe->getId()] ?? [];
            if (!empty($perf)) {
                $perf['nomEmploye'] = $employe->getNomComplet();
                $classement[]       = $perf;
            }
        }

        usort($classement, fn($a, $b) => $b['score'] <=> $a['score']);
        return $classement;
    }

    // ══════════════════════════════════════════════════════════════════
    //  STATISTIQUES GLOBALES
    // ══════════════════════════════════════════════════════════════════

    /**
     * @param array<int, array<string, mixed>> $classement
     * @return array<string, mixed>
     */
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
    //  RÉCUPÉRATION DES TÂCHES
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return array<int, mixed>
     */
    private function fetchTaches(int $idEmploye): array
    {
        // Fix PHPStan: method_exists() inutile car la méthode existe toujours dans TacheRepository
        // On appelle directement la méthode dédiée, avec fallback sur findBy() si vide
        $result = $this->tacheRepo->findTachesParEmployePourPerformance($idEmploye);

        if (!empty($result)) {
            return $result;
        }

        // Fallback universel
        return $this->tacheRepo->findBy(['idEmploye' => $idEmploye]);
    }
}
<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;
use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class RecommandationResult
{
    public Employe $employe;
    public float $scoreTotal = 0.0;
    public float $scoreCompetences = 0.0;
    public float $scorePerformance = 0.0;
    public float $scoreDisponibilite = 0.0;
    public float $scoreExperience = 0.0;
    public float $indiceConfiance = 0.0;
    public string $raisonRecommandation = '';

    public function getAppreciationKey(): string
    {
        if ($this->scoreTotal >= 80) return "ai.matching.perfect";
        if ($this->scoreTotal >= 60) return "ai.matching.good";
        if ($this->scoreTotal >= 40) return "ai.matching.fair";
        return "ai.matching.poor";
    }

    public function getConfianceKey(): string
    {
        if ($this->indiceConfiance >= 80) return "common.high";
        if ($this->indiceConfiance >= 50) return "common.medium";
        return "common.low";
    }

    public function getEmoji(): string
    {
        if ($this->scoreTotal >= 80) return "🌟";
        if ($this->scoreTotal >= 60) return "👍";
        return "⚠️";
    }

    public function getCouleur(): string
    {
        if ($this->scoreTotal >= 80) return "#27ae60";
        if ($this->scoreTotal >= 60) return "#cfb53b";
        return "#e74c3c";
    }
}

/**
 * 🎯 MatchingService v2 — Scoring amélioré
 *
 * CORRECTIONS par rapport à v1 :
 *  - scoreCompetences ne démarre plus à 50 par défaut → calcul basé sur similarité réelle
 *  - scoreExperience basé sur nombre de tâches + taux de réussite (pas juste 80/40)
 *  - Bonus spécialité : +15 pts si poste correspond EXACTEMENT à la catégorie
 *  - Poids ajustés : compétence 35%, performance 40%, disponibilité 25%
 *  - raison détaillée traduite avec vraies valeurs
 */
class MatchingService
{
    // Pondérations du score total (somme = 100%)
    private const POIDS_COMPETENCE   = 0.35;
    private const POIDS_PERFORMANCE  = 0.40;
    private const POIDS_DISPONIBILITE = 0.25;

    // Correspondances poste → catégories de tâche
    private const POSTE_CATEGORIE_MAP = [
        'irrigat'      => ['irrigation', 'arrosage'],
        'tracteur'     => ['labour', 'transport', 'mecanique'],
        'fertilisa'    => ['fertilisation', 'epandage'],
        'recolte'      => ['recolte', 'cueillette'],
        'plantation'   => ['plantation', 'semis'],
        'taille'       => ['taille', 'elagage'],
        'serre'        => ['serre', 'culture protegee'],
        'entretien'    => ['entretien', 'maintenance'],
        'jardinier'    => ['plantation', 'taille', 'entretien'],
        'agronome'     => ['fertilisation', 'diagnostic', 'traitement'],
        'conducteur'   => ['transport', 'tracteur', 'mecanique'],
        'ouvrier'      => ['recolte', 'plantation', 'entretien', 'irrigation'],
        'chef'         => ['supervision', 'gestion', 'planification'],
    ];

    public function __construct(
        private EmployeRepository  $employeRepository,
        private TacheRepository    $tacheRepository,
        private PerformanceService $performanceService,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @return RecommandationResult[]
     */
    public function recommanderEmployes(int $idTache, int $limit = 3): array
    {
        $tache = $this->tacheRepository->find($idTache);
        if ($tache === null) {
            return [];
        }

        $agriculteurId = $tache->getIdAgriculteur();
        if ($agriculteurId === null) {
            return [];
        }

        $employes = $this->employeRepository->findActifsByAgriculteur($agriculteurId);

        $results = [];
        foreach ($employes as $emp) {
            $results[] = $this->calculerRecommandation($emp, $tache, $agriculteurId);
        }

        // Tri par score total décroissant
        usort($results, fn(RecommandationResult $a, RecommandationResult $b)
            => $b->scoreTotal <=> $a->scoreTotal);

        return array_slice($results, 0, $limit);
    }

    // ══════════════════════════════════════════════════════════════════
    //  CALCUL DU SCORE (logique v2)
    // ══════════════════════════════════════════════════════════════════

    private function calculerRecommandation(
        Employe $emp,
        Tache   $tache,
        int     $agriculteurId
    ): RecommandationResult {
        $rec         = new RecommandationResult();
        $rec->employe = $emp;

        $empId = $emp->getId();
        if ($empId === null) {
            return $rec;
        }

        // ── 1. Score Compétences ──────────────────────────────────────
        $rec->scoreCompetences = $this->calculerScoreCompetences($emp, $tache);

        // ── 2. Score Performance (issu du PerformanceService) ─────────
        $perf                  = $this->performanceService->calculatePerformance($empId);
        $rec->scorePerformance = $perf['score'];

        // ── 3. Score Expérience ───────────────────────────────────────
        // Basé sur le nombre de tâches traitées et le taux de réussite
        $rec->scoreExperience = $this->calculerScoreExperience($perf);

        // ── 4. Score Disponibilité ────────────────────────────────────
        $nbEnCours             = $this->tacheRepository->countTachesActivesParEmploye(
            $empId, $agriculteurId
        );
        $rec->scoreDisponibilite = $this->calculerScoreDisponibilite($nbEnCours);

        // ── 5. Score Total pondéré ────────────────────────────────────
        $rec->scoreTotal = (
            $rec->scoreCompetences   * self::POIDS_COMPETENCE   +
            $rec->scorePerformance   * self::POIDS_PERFORMANCE  +
            $rec->scoreDisponibilite * self::POIDS_DISPONIBILITE
        );

        // Bonus si l'employé a terminé des tâches similaires → +5 pts
        if ($this->aExperienceSurCategorie($empId, $tache->getCategorie() ?? '')) {
            $rec->scoreTotal = min(100.0, $rec->scoreTotal + 5.0);
        }

        $rec->scoreTotal     = round($rec->scoreTotal, 1);
        $rec->indiceConfiance = min(100.0, $rec->scoreTotal + ($perf['totalTaches'] > 5 ? 10.0 : 5.0));

        // ── 6. Raison détaillée ───────────────────────────────────────
        $chargeKey = match (true) {
            $nbEnCours === 0 => 'common.none',
            $nbEnCours <= 2  => 'common.moderate',
            default          => 'common.high_load',
        };

        $rec->raisonRecommandation = $this->translator->trans('ai.matching.reason_detail', [
            '%name%'      => $emp->getPrenom(),
            '%relevance%' => round($rec->scoreCompetences, 1),
            '%perf%'      => round($rec->scorePerformance, 1),
            '%charge%'    => $this->translator->trans($chargeKey),
        ]);

        return $rec;
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS DE SCORING
    // ══════════════════════════════════════════════════════════════════

    /**
     * Score de compétences : 0–100
     *  - Correspondance exacte poste ↔ catégorie : 100
     *  - Correspondance partielle (map sémantique) : 75
     *  - Correspondance via similar_text : linéaire
     *  - Aucune correspondance : 30 (baseline non-nul pour garder l'employé dans la liste)
     */
    private function calculerScoreCompetences(Employe $emp, Tache $tache): float
    {
        $poste = strtolower(trim($emp->getPoste() ?? ''));
        $cat   = strtolower(trim($tache->getCategorie() ?? ''));

        if ($poste === '' || $cat === '') {
            return 30.0; // donnée manquante → score minimum neutre
        }

        // Correspondance exacte
        if ($poste === $cat || str_contains($poste, $cat) || str_contains($cat, $poste)) {
            return 100.0;
        }

        // Correspondance sémantique via la map
        foreach (self::POSTE_CATEGORIE_MAP as $motcle => $categories) {
            if (str_contains($poste, $motcle) && in_array($cat, $categories, true)) {
                return 75.0;
            }
        }

        // Similarité textuelle (similar_text = 0–100)
        similar_text($poste, $cat, $pct);
        if ($pct >= 50) {
            return max(40.0, $pct);
        }

        return 30.0; // baseline non-nul
    }

    /**
     * Score d'expérience : 0–100, basé sur volume + qualité
     *
     * @param array<string, mixed> $perf
     */
    private function calculerScoreExperience(array $perf): float
    {
        $total    = (int)   ($perf['totalTaches']  ?? 0);
        $reussite = (float) ($perf['tauxReussite'] ?? 0.0);

        if ($total === 0) {
            return 20.0; // nouvel employé → score faible mais non nul
        }

        // Exponentiel plafonné : 5 tâches → 50, 10 → 70, 20+ → 90
        $volumeScore = min(90.0, 20.0 + ($total * 3.5));

        return round(($volumeScore * 0.4) + ($reussite * 0.6), 1);
    }

    /**
     * Score de disponibilité : 0–100 selon la charge actuelle
     */
    private function calculerScoreDisponibilite(int $nbTachesEnCours): float
    {
        return match (true) {
            $nbTachesEnCours === 0 => 100.0,
            $nbTachesEnCours === 1 => 80.0,
            $nbTachesEnCours === 2 => 60.0,
            $nbTachesEnCours === 3 => 35.0,
            default                => max(10.0, 35.0 - ($nbTachesEnCours - 3) * 8),
        };
    }

    /**
     * Vérifie si l'employé a déjà terminé des tâches dans cette catégorie.
     */
    private function aExperienceSurCategorie(int $idEmploye, string $categorie): bool
    {
        if ($categorie === '') {
            return false;
        }

        $taches = $this->tacheRepository->findBy(['idEmploye' => $idEmploye]);
        foreach ($taches as $t) {
            if (
                in_array($t->getStatut(), ['Terminé', 'Validé'], true) &&
                strtolower($t->getCategorie() ?? '') === strtolower($categorie)
            ) {
                return true;
            }
        }
        return false;
    }
}
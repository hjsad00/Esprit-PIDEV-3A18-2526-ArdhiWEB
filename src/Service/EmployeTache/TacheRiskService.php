<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\TacheRepository;

/**
 * 🔮 Service d'Analyse IA ─ Prédiction de risque de retard
 * Traduction exacte de TacheRiskAnalyzer.java
 */
class TacheRiskService
{
    public function __construct(
        private TacheRepository $tacheRepo
    ) {}

    public function analyser(Tache $tache, string $nomEmploye): array
    {
        $facteurs = [];
        $recommandations = [];

        // ── 1. Historique retards employé (30%) ──────────────────────
        $scoreHistorique = 50.0;
        if ($tache->getIdEmploye() !== null) {
            $scoreHistorique = $this->calculerScoreHistorique($tache->getIdEmploye(), $facteurs);
        } else {
            $facteurs[] = "⚠️ Aucun employé assigné — historique indisponible";
        }

        // ── 2. Charge actuelle (20%) ────────────────────────────────
        $scoreCharge = 50.0;
        if ($tache->getIdEmploye() !== null && $tache->getId() !== null) {
            $scoreCharge = $this->calculerScoreCharge($tache->getIdEmploye(), $tache->getId(), $facteurs);
        }

        // ── 3. Complexité tâche (25%) ───────────────────────────────
        $scoreComplexite = $this->calculerScoreComplexite($tache, $facteurs);

        // ── 4. Facteur saisonnier (10%) ─────────────────────────────
        $scoreSaison = $this->calculerScoreSaisonnier($tache, $facteurs);

        // ── 5. Pression délai (15%) ─────────────────────────────────
        $scoreDelai = $this->calculerScoreDelai($tache, $facteurs);

        // ── Calcul final pondéré ──────────────────────────────────────────
        $riskScore =
            ($scoreHistorique * 0.30)
            + ($scoreCharge     * 0.20)
            + ($scoreComplexite * 0.25)
            + ($scoreSaison     * 0.10)
            + ($scoreDelai      * 0.15);

        $riskScore = max(0.0, min(100.0, $riskScore));

        // ── Recommandations ─────────────────────────────────
        $this->genererRecommandations($riskScore, $scoreHistorique, $scoreCharge, $scoreDelai, $scoreComplexite, $nomEmploye, $tache, $recommandations);

        $result = [
            'riskScore' => round($riskScore, 1),
            'probabiliteReussite' => round(100 - $riskScore, 1),
            'facteurs' => $facteurs,
            'recommandations' => $recommandations,
            'scoreHistorique' => round($scoreHistorique, 1),
            'scoreCharge' => round($scoreCharge, 1),
            'scoreComplexite' => round($scoreComplexite, 1),
            'scoreSaison' => round($scoreSaison, 1),
            'scoreDelai' => round($scoreDelai, 1),
        ];

        // Niveau et cosmétique
        if ($riskScore < 30) {
            $result['niveau'] = "FAIBLE";
            $result['couleur'] = "#27ae60";
            $result['emoji'] = "🟢";
        } elseif ($riskScore < 55) {
            $result['niveau'] = "MODÉRÉ";
            $result['couleur'] = "#f39c12";
            $result['emoji'] = "🟡";
        } elseif ($riskScore < 75) {
            $result['niveau'] = "ÉLEVÉ";
            $result['couleur'] = "#e67e22";
            $result['emoji'] = "🟠";
        } else {
            $result['niveau'] = "CRITIQUE";
            $result['couleur'] = "#e74c3c";
            $result['emoji'] = "🔴";
        }

        return $result;
    }

    private function calculerScoreHistorique(int $idEmploye, array &$facteurs): float
    {
        $historique = $this->tacheRepo->findHistoriquePourRisque($idEmploye);
        $total = count($historique);

        if ($total === 0) {
            $facteurs[] = "📊 Historique : aucune tâche passée — données insuffisantes";
            return 40.0;
        }

        $retards = 0;
        foreach ($historique as $t) {
            // Un retard est compté si la date de modification (clôture) est après la date de fin
            if ($t->getDateFin() !== null && $t->getDateModification() > $t->getDateFin()) {
                $retards++;
            }
        }

        $tauxRetard = ($retards / $total) * 100;

        if ($tauxRetard == 0) {
            $facteurs[] = "✅ Historique : 0% de retards sur $total tâche(s) — excellent";
        } elseif ($tauxRetard < 20) {
            $facteurs[] = sprintf("📊 Historique : %.0f%% de retards (%d/%d tâches)", $tauxRetard, $retards, $total);
        } elseif ($tauxRetard < 50) {
            $facteurs[] = sprintf("⚠️ Historique : %.0f%% de retards (%d/%d tâches)", $tauxRetard, $retards, $total);
        } else {
            $facteurs[] = sprintf("🔴 Historique : %.0f%% de retards (%d/%d tâches) — préoccupant", $tauxRetard, $retards, $total);
        }

        return $tauxRetard;
    }

    private function calculerScoreCharge(int $idEmploye, int $idTache, array &$facteurs): float
    {
        $tachesActives = $this->tacheRepo->countChargeActuelle($idEmploye, $idTache);

        if ($tachesActives === 0) {
            $score = 10.0; $label = "Charge : aucune autre tâche active — disponible ✅";
        } elseif ($tachesActives <= 2) {
            $score = 25.0; $label = "Charge : $tachesActives tâche(s) active(s) — charge légère";
        } elseif ($tachesActives <= 4) {
            $score = 55.0; $label = "⚠️ Charge : $tachesActives tâches actives — charge modérée";
        } elseif ($tachesActives <= 6) {
            $score = 75.0; $label = "🔶 Charge : $tachesActives tâches actives — charge élevée";
        } else {
            $score = 92.0; $label = "🔴 Charge : $tachesActives tâches actives — surcharge critique";
        }

        $facteurs[] = $label;
        return $score;
    }

    private function calculerScoreComplexite(Tache $tache, array &$facteurs): float
    {
        $score = 30.0;

        // Priorité
        $prio = $tache->getPriorite();
        switch ($prio) {
            case 1: $score += 0;  $facteurs[] = "📌 Priorité : Basse — impact faible"; break;
            case 2: $score += 15; $facteurs[] = "📌 Priorité : Moyenne"; break;
            case 3: $score += 35; $facteurs[] = "⚠️ Priorité : Haute — complexité accrue"; break;
            case 4: $score += 55; $facteurs[] = "🔴 Priorité : Critique — risque maximal"; break;
            default: $score += 20; $facteurs[] = "📌 Priorité : non définie";
        }

        // Catégorie
        $cat = $tache->getCategorie();
        $bonus = match ($cat) {
            'Récolte' => 20,
            'Plantation' => 15,
            'Irrigation' => 5,
            'Fertilisation' => 10,
            'Maintenance' => 8,
            default => 5,
        };
        
        $catLabels = [
            'Récolte' => "🌾 Catégorie : Récolte — dépendance météo forte",
            'Plantation' => "🌱 Catégorie : Plantation — timing critique",
            'Irrigation' => "💧 Catégorie : Irrigation — technique modérée",
            'Fertilisation' => "🧪 Catégorie : Fertilisation — précision requise",
            'Maintenance' => "🔧 Catégorie : Maintenance — risque standard",
        ];
        
        $facteurs[] = $catLabels[$cat] ?? "📂 Catégorie : $cat";
        $score += $bonus;

        return min(100.0, $score);
    }

    private function calculerScoreSaisonnier(Tache $tache, array &$facteurs): float
    {
        $date = $tache->getDateDebut() ?? new \DateTime();
        $mois = (int)$date->format('m');

        switch ($mois) {
            case 6: case 7: case 8:
                $facteurs[] = "☀️ Saison : été — pic de chaleur, risque agricole élevé";
                return 80.0;
            case 3: case 4: case 5:
                $facteurs[] = "🌸 Saison : printemps — période de plantation intense";
                return 65.0;
            case 9: case 10:
                $facteurs[] = "🍂 Saison : automne — période de récolte active";
                return 60.0;
            case 11: case 12:
                $facteurs[] = "🌧️ Saison : début hiver — activité réduite";
                return 30.0;
            default:
                $facteurs[] = "❄️ Saison : hiver — activité minimale";
                return 20.0;
        }
    }

    private function calculerScoreDelai(Tache $tache, array &$facteurs): float
    {
        if ($tache->getDateFin() === null) {
            $facteurs[] = "📅 Délai : aucune date de fin définie — risque neutre";
            return 50.0;
        }

        $debut = $tache->getDateDebut() ?? new \DateTime();
        $fin = $tache->getDateFin();
        $now = new \DateTime('today');

        $dureeTotal = $debut->diff($fin)->days;
        $joursRestants = $now->diff($fin)->days;
        if ($fin < $now) $joursRestants = -1 * $joursRestants;

        if ($joursRestants < 0) {
            $facteurs[] = "🔴 Délai : deadline dépassée de " . abs($joursRestants) . " jour(s) !";
            return 100.0;
        }

        if ($dureeTotal <= 0) $dureeTotal = 1;
        $avancement = 1.0 - ($joursRestants / $dureeTotal);

        if ($joursRestants == 0) {
            $score = 95.0; $facteurs[] = "🔴 Délai : deadline AUJOURD'HUI !";
        } elseif ($joursRestants <= 2) {
            $score = 85.0; $facteurs[] = "🔶 Délai : seulement $joursRestants jour(s) restant(s) — urgent";
        } elseif ($joursRestants <= 7) {
            $score = 65.0; $facteurs[] = "⚠️ Délai : $joursRestants jours restants (semaine courante)";
        } elseif ($dureeTotal <= 3) {
            $score = 60.0; $facteurs[] = "⚠️ Délai : tâche courte ($dureeTotal jours total)";
        } elseif ($avancement > 0.75) {
            $score = 55.0; $facteurs[] = "⚠️ Délai : $joursRestants j restants / $dureeTotal j total — phase finale";
        } else {
            $score = 20.0; $facteurs[] = "✅ Délai : $joursRestants jours restants — confortable";
        }

        return $score;
    }

    private function genererRecommandations(float $riskScore, float $scoreHist, float $scoreCharge, float $scoreDelai, float $scoreComp, string $nomEmp, Tache $tache, array &$recos): void
    {
        if ($riskScore < 30) {
            $recos[] = "✅ Tâche bien planifiée — aucune action urgente requise.";
            $recos[] = "📋 Maintenir le suivi hebdomadaire habituel.";
            return;
        }

        if ($scoreHist > 60) {
            $recos[] = "📌 Planifier un point de suivi quotidien avec $nomEmp.";
            $recos[] = "🔄 Envisager de réaffecter partiellement la tâche à un autre employé.";
        }

        if ($scoreCharge > 60) {
            $recos[] = "⚖️ Alléger la charge de $nomEmp — suspendre ou déléguer une tâche moins prioritaire.";
        }

        if ($scoreDelai > 70) {
            $recos[] = "📅 Prolonger la date limite ou mobiliser des ressources supplémentaires.";
            if ($tache->getDateFin() !== null) {
                $nouvelleDate = (clone $tache->getDateFin())->modify('+3 days');
                $recos[] = "💡 Suggestion : repousser la deadline au " . $nouvelleDate->format('d/m/Y') . " (+3 jours).";
            }
        }

        if ($scoreComp > 70) {
            $recos[] = "🧩 Décomposer cette tâche complexe en sous-tâches plus petites.";
        }

        if ($riskScore >= 75) {
            $recos[] = "🚨 ALERTE : Risque critique — intervention immédiate recommandée.";
            $recos[] = "📞 Contacter directement l'employé pour évaluer la situation.";
        }

        if (empty($recos)) {
            $recos[] = "👁️ Surveiller l'avancement de cette tâche de près.";
            $recos[] = "📊 Vérifier l'état dans 2–3 jours.";
        }
    }
}

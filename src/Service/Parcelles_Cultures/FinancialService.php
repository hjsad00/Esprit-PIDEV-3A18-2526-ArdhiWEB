<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use App\Entity\Parcelles_Cultures\Parcelle;

class FinancialService
{
    /**
     * ET0 = 0.0023 × (T + 17.8) × sqrt(Tmax − Tmin)
     * Evapotranspiration de référence
     */
    public function calculerET0(float $temperatureMoyenne, float $temperatureMax, float $temperatureMin): float
    {
        if ($temperatureMax <= $temperatureMin) {
            return 0;
        }
        return 0.0023 * ($temperatureMoyenne + 17.8) * sqrt($temperatureMax - $temperatureMin);
    }

    /**
     * FacteurClimatique = max(0.5, 1 − 0.01×JoursCanicule − 0.005×JoursExcesPluie − 0.02×JoursGel)
     */
    public function calculerFacteurClimatique(int $joursCanicule, int $joursExcesPluie, int $joursGel): float
    {
        $facteur = 1
            - (0.01 * $joursCanicule)
            - (0.005 * $joursExcesPluie)
            - (0.02 * $joursGel);

        return max(0.5, $facteur);
    }

    /**
     * ProductionTheorique = Surface × RendementTheorique
     * ProductionReelle = ProductionTheorique × FacteurClimatique
     */
    public function calculerProductionReelle(
        float $surface,
        float $rendementTheorique,
        float $facteurClimatique
    ): float {
        $productionTheorique = $surface * $rendementTheorique;
        return $productionTheorique * $facteurClimatique;
    }

    /**
     * CoutTotal = Semences + Engrais + MainOeuvre + Irrigation + Autres
     */
    public function calculerCoutTotal(
        float $coutSemences,
        float $coutEngrais,
        float $coutMainOeuvre,
        float $coutIrrigation,
        float $coutAutres
    ): float {
        return $coutSemences + $coutEngrais + $coutMainOeuvre + $coutIrrigation + $coutAutres;
    }

    /**
     * RevenuBrut = ProductionReelle × PrixVente
     */
    public function calculerRevenuBrut(float $productionReelle, float $prixVente): float
    {
        return $productionReelle * $prixVente;
    }

    /**
     * MargeBrute = RevenuBrut − CoutTotal
     */
    public function calculerMargeBrute(float $revenuBrut, float $coutTotal): float
    {
        return $revenuBrut - $coutTotal;
    }

    /**
     * PrixSeuil = CoutTotal / ProductionReelle
     */
    public function calculerPrixSeuil(float $coutTotal, float $productionReelle): float
    {
        if ($productionReelle == 0) {
            return 0;
        }
        return $coutTotal / $productionReelle;
    }

    /**
     * ScoreROI = (MargeBrute / CoutTotal) × 100
     */
    public function calculerScoreROI(float $margeBrute, float $coutTotal): float
    {
        if ($coutTotal == 0) {
            return 0;
        }
        return ($margeBrute / $coutTotal) * 100;
    }

    /**
     * Calculer ROI complet
     */
    public function calculerRoi(
        Culture $culture,
        Parcelle $parcelle,
        float $prixVente,
        float $coutSemences,
        float $coutEngrais,
        float $coutMainOeuvre,
        float $coutIrrigation,
        float $coutAutres,
        float $joursCanicule = 0,
        float $joursExcesPluie = 0,
        float $joursGel = 0
    ): array {
        $surface = (float) $culture->getSurfaceUtilisee();
        $rendement = (float) $culture->getRendementEstime();

        $facteurClimatique = $this->calculerFacteurClimatique(
            (int) $joursCanicule,
            (int) $joursExcesPluie,
            (int) $joursGel
        );

        $productionReelle = $this->calculerProductionReelle($surface, $rendement, $facteurClimatique);
        $coutTotal = $this->calculerCoutTotal($coutSemences, $coutEngrais, $coutMainOeuvre, $coutIrrigation, $coutAutres);
        $revenuBrut = $this->calculerRevenuBrut($productionReelle, $prixVente);
        $margeBrute = $this->calculerMargeBrute($revenuBrut, $coutTotal);
        $prixSeuil = $this->calculerPrixSeuil($coutTotal, $productionReelle);
        $scoreROI = $this->calculerScoreROI($margeBrute, $coutTotal);

        return [
            'production_theorique' => $surface * $rendement,
            'facteur_climatique' => $facteurClimatique,
            'production_reelle' => $productionReelle,
            'cout_total' => $coutTotal,
            'revenu_brut' => $revenuBrut,
            'marge_brute' => $margeBrute,
            'prix_seuil' => $prixSeuil,
            'score_roi' => $scoreROI,
        ];
    }
}

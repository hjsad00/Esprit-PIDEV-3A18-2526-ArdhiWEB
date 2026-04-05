<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;

/**
 * Service pour les calculs de financement et ROI
 */
class FinancialService
{
    /**
     * Calcule le facteur climatique según la saison
     * (0.8 saison sèche, 0.95 autres)
     */
    public function calculerFacteurClimatique(Culture $culture): float
    {
        return $culture->getSaison() === 'Saison sèche' ? 0.8 : 0.95;
    }

    /**
     * Calcule la production réelle selon les conditions
     */
    public function calculerProductionReelle(Culture $culture): float
    {
        $productionEstimee = $culture->getSurfaceUtilisee() * $culture->getRendementEstime();
        $facteurClimatique = $this->calculerFacteurClimatique($culture);
        return $productionEstimee * $facteurClimatique;
    }

    /**
     * Calcule le coût total de production
     * Coûts fixes: 150€/ha, coûts variables: 0.5€/kg
     */
    public function calculerCoutTotal(Culture $culture): float
    {
        $coutFixe = $culture->getSurfaceUtilisee() * 150;
        $productionReelle = $this->calculerProductionReelle($culture);
        $coutVariable = $productionReelle * 0.5;
        return $coutFixe + $coutVariable;
    }

    /**
     * Calcule le revenu brut (production * prix de marché)
     * Supposition: 0.8€/kg
     */
    public function calculerRevenuBrut(Culture $culture): float
    {
        $productionReelle = $this->calculerProductionReelle($culture);
        return $productionReelle * 0.8;
    }

    /**
     * Calcule la marge brute (revenu brut - coûts)
     */
    public function calculerMargeBrute(Culture $culture): float
    {
        return max(0, $this->calculerRevenuBrut($culture) - $this->calculerCoutTotal($culture));
    }

    /**
     * Calcule le prix seuil de rentabilité
     */
    public function calculerPrixSeuil(Culture $culture): float
    {
        $productionReelle = $this->calculerProductionReelle($culture);
        if ($productionReelle == 0) return 0;

        return $this->calculerCoutTotal($culture) / $productionReelle;
    }

    /**
     * Calcule le score ROI (0-100)
     * Basé sur la marge brute / coûts totaux
     */
    public function calculerScoreRoi(Culture $culture): float
    {
        $coutTotal = $this->calculerCoutTotal($culture);
        if ($coutTotal == 0) {
            return 0;
        }

        $margeBrute = $this->calculerMargeBrute($culture);
        $roiRatio = $margeBrute / $coutTotal;
        $score = min(100, $roiRatio * 100);

        return max(0, $score);
    }

    /**
     * Calcule le ROI en pourcentage
     */
    public function calculerRoi(Culture $culture): float
    {
        $coutTotal = $this->calculerCoutTotal($culture);
        if ($coutTotal == 0) {
            return 0;
        }

        $margeBrute = $this->calculerMargeBrute($culture);
        return ($margeBrute / $coutTotal) * 100;
    }
}

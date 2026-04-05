<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;

/**
 * Service pour les calculs d'irrigation
 * Utilise la formule de Penman-Monteith modifiée pour ET0
 */
class IrrigationService
{
    /**
     * Calcule l'évapotranspiration de référence (ET0) en mm/jour
     * Supposition simple: 4mm/jour * facteur saisonnier
     */
    public function calculerET0(Culture $culture): float
    {
        $baseET = 4.0; // mm/jour
        
        $facteurSaison = $culture->getSaison() === 'Saison sèche' ? 1.5 : 1.0;
        
        return $baseET * $facteurSaison;
    }

    /**
     * Calcule le besoin brut d'irrigation en mm
     * Besoin = ET0 * Kc * nombre_jours
     */
    public function calculerBesoinBrut(Culture $culture, int $joursVegetation): float
    {
        $et0 = $this->calculerET0($culture);
        $kc = $this->getKcByPhase($culture);
        $joursMois = 30; // Estimation: 30 jours
        
        return $et0 * $kc * $joursMois;
    }

    /**
     * Calcule le besoin net selon l'efficacité du système d'irrigation
     * (efficacité: goutte-à-goutte 90%, aspersion 75%, rainage 70%, manuel 50%)
     */
    public function calculerBesoinNet(Culture $culture, int $joursVegetation): float
    {
        $besoinBrut = $this->calculerBesoinBrut($culture, $joursVegetation);
        
        $efficacites = [
            'Goutte à goutte' => 0.9,
            'Aspersion' => 0.75,
            'Rainage' => 0.7,
            'Manuel' => 0.5
        ];
        
        $efficacite = $efficacites[$culture->getParcelle()->getSystemeIrrigation()] ?? 0.7;
        
        return $besoinBrut / $efficacite;
    }

    /**
     * Convertit le besoin en litre (mm/hectare = 10,000 litres)
     */
    public function calculerVolumeLitres(Culture $culture, int $joursVegetation): float
    {
        $besoinNet = $this->calculerBesoinNet($culture, $joursVegetation);
        $surface = $culture->getSurfaceUtilisee();
        
        // 1 mm sur 1 hectare = 10,000 litres
        return $besoinNet * $surface * 10000;
    }

    /**
     * Convertit le besoin en mètres cubes
     */
    public function calculerVolumeCubique(Culture $culture, int $joursVegetation): float
    {
        $litres = $this->calculerVolumeLitres($culture, $joursVegetation);
        return $litres / 1000;
    }

    /**
     * Calcule l'irrigation complète pour une culture
     */
    public function calculerIrrigation(Culture $culture, int $joursVegetation): array
    {
        $et0 = $this->calculerET0($culture);
        $kc = $this->getKcByPhase($culture);
        $besoinBrut = $this->calculerBesoinBrut($culture, $joursVegetation);
        $besoinNet = $this->calculerBesoinNet($culture, $joursVegetation);
        $volumeLitres = $this->calculerVolumeLitres($culture, $joursVegetation);
        $volumeM3 = $this->calculerVolumeCubique($culture, $joursVegetation);
        $seuilIrrigation = $this->getSeuilIrrigationByType($culture);

        return [
            'et0' => round($et0, 2),
            'kc' => round($kc, 2),
            'besoin_brut_mm' => round($besoinBrut, 2),
            'besoin_net_mm' => round($besoinNet, 2),
            'volume_litres' => round($volumeLitres, 2),
            'volume_m3' => round($volumeM3, 2),
            'seuil_irrigation' => $seuilIrrigation,
            'systeme_irrigation' => $culture->getParcelle()->getSystemeIrrigation()
        ];
    }

    /**
     * Récupère le coefficient cultural (Kc) selon le type de culture et la phase
     */
    private function getKcByPhase(Culture $culture): float
    {
        // Supposition: cultures à mi-saison
        $kc_cible = [
            'Légume' => 0.8,
            'Céréale' => 1.0,
            'Fruit' => 0.9,
            'Fourrage' => 0.85,
            'Légumineuse' => 0.75
        ];

        return $kc_cible[$culture->getTypeCulture()] ?? 0.8;
    }

    /**
     * Récupère le seuil d'irrigation selon le type de culture
     */
    private function getSeuilIrrigationByType(Culture $culture): int
    {
        $seuils = [
            'Légume' => 40,
            'Céréale' => 50,
            'Fruit' => 45,
            'Fourrage' => 35,
            'Légumineuse' => 45
        ];

        return $seuils[$culture->getTypeCulture()] ?? 40;
    }
}

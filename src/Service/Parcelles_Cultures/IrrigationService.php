<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use App\Entity\Parcelles_Cultures\Parcelle;

class IrrigationService
{
    /**
     * ET0 = 0.0023 × (T + 17.8) × sqrt(Tmax − Tmin)
     */
    private function calculerET0(float $temperatureMoyenne, float $temperatureMax, float $temperatureMin): float
    {
        if ($temperatureMax <= $temperatureMin) {
            return 0;
        }
        return 0.0023 * ($temperatureMoyenne + 17.8) * sqrt($temperatureMax - $temperatureMin);
    }

    /**
     * BesoinBrut = Kc × ET0
     */
    private function calculerBesoinBrut(float $kc, float $et0): float
    {
        return $kc * $et0;
    }

    /**
     * BesoinNet = max(0, BesoinBrut − Précipitations)
     */
    private function calculerBesoinNet(float $besoinBrut, float $precipitations): float
    {
        return max(0, $besoinBrut - $precipitations);
    }

    /**
     * VolumeLitres = BesoinNet × SurfaceHa × 10000
     */
    private function calculerVolumeLitres(float $besoinNet, float $surfaceHa): float
    {
        return $besoinNet * $surfaceHa * 10000;
    }

    /**
     * Calcul complet d'irrigation
     */
    public function calculerIrrigation(
        Culture $culture,
        Parcelle $parcelle,
        float $temperatureMoyenne,
        float $temperatureMax,
        float $temperatureMin,
        float $precipitations,
        float $humidite,
        float $kc
    ): array {
        $surface = (float) $culture->getSurfaceUtilisee();
        $et0 = $this->calculerET0($temperatureMoyenne, $temperatureMax, $temperatureMin);
        $besoinBrut = $this->calculerBesoinBrut($kc, $et0);
        $besoinNet = $this->calculerBesoinNet($besoinBrut, $precipitations);
        $volumeLitres = $this->calculerVolumeLitres($besoinNet, $surface);

        return [
            'et0' => $et0,
            'besoin_brut' => $besoinBrut,
            'besoin_net' => $besoinNet,
            'volume_litres' => $volumeLitres,
            'volume_m3' => $volumeLitres / 1000,
            'temperature_moyenne' => $temperatureMoyenne,
            'precipitations' => $precipitations,
            'humidite' => $humidite,
        ];
    }
}

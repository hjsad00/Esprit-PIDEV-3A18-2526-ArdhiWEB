<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class IrrigationService
{
    private HttpClientInterface $httpClient;

    // Catalogue Kc par culture
    private const KC_CATALOG = [
        'ble' => 0.25,
        'orge' => 0.20,
        'avoine' => 0.22,
        'mais' => 0.35,
        'sorgho' => 0.28,
        'pois_chiche' => 0.28,
        'feve' => 0.22,
        'lentille' => 0.18,
        'haricot' => 0.30,
        'soja' => 0.32,
        'tomate' => 0.40,
        'piment' => 0.38,
        'courgette' => 0.35,
        'concombre' => 0.42,
        'pasteque' => 0.30,
        'melon' => 0.32,
        'olive' => 0.12,
        'tournesol' => 0.30,
        'luzerne' => 0.45,
    ];

    // Météo fallback par saison (Tunisie)
    private const METEO_FALLBACK = [
        'printemps' => ['tmoy' => 18, 'tmax' => 24, 'tmin' => 12, 'precip' => 8, 'humidite' => 60],
        'ete' => ['tmoy' => 30, 'tmax' => 37, 'tmin' => 23, 'precip' => 1, 'humidite' => 45],
        'automne' => ['tmoy' => 20, 'tmax' => 26, 'tmin' => 14, 'precip' => 12, 'humidite' => 65],
        'hiver' => ['tmoy' => 11, 'tmax' => 16, 'tmin' => 6, 'precip' => 18, 'humidite' => 75],
    ];

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Calcule les besoins d'irrigation avec formules agronomiques
     */
    public function calculateIrrigationNeeds(float $latitude, float $longitude, string $culture, float $surface, string $saison = null): array
    {
        // Récupérer météo réelle ou fallback
        $meteo = $this->getMeteoData($latitude, $longitude, $saison);

        // Calculer ET0 (Hargreaves)
        $et0 = $this->calculateET0($meteo['tmoy'], $meteo['tmax'], $meteo['tmin']);

        // Coefficient de culture Kc
        $kc = $this->getKcValue($culture);

        // Besoin brut = Kc × ET0
        $besoinBrut = $kc * $et0;

        // Besoin net = max(0, BesoinBrut - Précipitations)
        $besoinNet = max(0, $besoinBrut - $meteo['precip']);

        // Volume eau en litres = BesoinNet × Surface(ha) × 10000
        $volumeEauLitres = $besoinNet * $surface * 10000;
        $volumeEauM3 = $volumeEauLitres / 1000;

        // Stress hydrique
        $stressIndex = $meteo['tmoy'] / ($meteo['humidite'] + 1);
        $stressDetecte = ($meteo['tmoy'] > 35 && $meteo['precip'] < 5) || ($stressIndex > 0.5);
        $causeStress = '';
        if ($stressDetecte) {
            if ($meteo['tmoy'] > 35 && $meteo['precip'] < 5) {
                $causeStress = 'Température élevée + faibles précipitations';
            } else {
                $causeStress = 'Indice de stress élevé';
            }
        }

        // Niveau d'irrigation
        $niveauIrrigation = $this->getNiveauIrrigation($volumeEauLitres);

        // Efficacité hydrique (nécessite rendement - placeholder)
        $efficaciteHydrique = $volumeEauM3 > 0 ? 0 : 0;

        // Conseil principal
        $conseilPrincipal = $this->getConseilPrincipal($niveauIrrigation, $stressDetecte, $meteo);

        return [
            'temperatureMoyenne' => round($meteo['tmoy'], 1),
            'temperatureMax' => round($meteo['tmax'], 1),
            'temperatureMin' => round($meteo['tmin'], 1),
            'precipitationsSemaine' => round($meteo['precip'], 1),
            'humidite' => round($meteo['humidite'], 1),
            'descriptionMeteo' => $meteo['description'] ?? 'Données météo',
            'donneesFallback' => !$meteo['reel'] ?? false,
            'kcCulture' => $kc,
            'et0' => round($et0, 2),
            'besoinBrut' => round($besoinBrut, 2),
            'besoinNet' => round($besoinNet, 2),
            'volumeEauLitres' => round($volumeEauLitres, 0),
            'volumeEauM3' => round($volumeEauM3, 2),
            'surfaceHectares' => $surface,
            'stressHydriqueDetecte' => $stressDetecte,
            'causeStress' => $causeStress,
            'niveauIrrigation' => $niveauIrrigation,
            'efficaciteHydrique' => $efficaciteHydrique,
            'conseilPrincipal' => $conseilPrincipal,
            'stressIndex' => round($stressIndex, 2),
        ];
    }

    /**
     * Calcule ET0 selon la formule de Hargreaves
     * ET0 = 0.0023 × (Tmoy + 17.8) × √(Tmax - Tmin)
     */
    private function calculateET0(float $tmoy, float $tmax, float $tmin): float
    {
        $deltaT = $tmax - $tmin;
        if ($deltaT <= 0) $deltaT = 5; // Valeur par défaut
        
        return 0.0023 * ($tmoy + 17.8) * sqrt($deltaT);
    }

    /**
     * Retourne la valeur Kc (coefficient de culture)
     */
    private function getKcValue(string $culture): float
    {
        $culture = strtolower(str_replace([' ', '_'], '_', $culture));
        return self::KC_CATALOG[$culture] ?? 0.28; // Défaut = 0.28
    }

    /**
     * Récupère les données météo (API Open-Meteo ou fallback)
     */
    private function getMeteoData(float $lat, float $lon, ?string $saison = null): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
                    'hourly' => 'relativehumidity_2m',
                    'forecast_days' => 7,
                    'timezone' => 'Africa/Tunis',
                ]
            ]);

            $data = $response->toArray();
            
            if (isset($data['daily']) && isset($data['hourly'])) {
                $daily = $data['daily'];
                $tmoy = ($daily['temperature_2m_max'][0] + $daily['temperature_2m_min'][0]) / 2;
                $precip = $daily['precipitation_sum'][0] ?? 0;
                $humidite = $data['hourly']['relativehumidity_2m'][0] ?? 60;

                return [
                    'tmoy' => $tmoy,
                    'tmax' => $daily['temperature_2m_max'][0],
                    'tmin' => $daily['temperature_2m_min'][0],
                    'precip' => $precip,
                    'humidite' => $humidite,
                    'description' => 'Données météo en temps réel',
                    'reel' => true,
                ];
            }
        } catch (\Exception $e) {
            // Fallback si API échoue
        }

        // Fallback Tunisie par saison
        $saison = $saison ?? $this->getSaisonActuelle();
        $fallback = self::METEO_FALLBACK[$saison] ?? self::METEO_FALLBACK['ete'];

        return array_merge($fallback, [
            'description' => "Données météo par défaut ({$saison})",
            'reel' => false,
        ]);
    }

    /**
     * Détermine la saison actuelle
     */
    private function getSaisonActuelle(): string
    {
        $mois = (int) date('m');
        if ($mois >= 3 && $mois <= 5) return 'printemps';
        if ($mois >= 6 && $mois <= 8) return 'ete';
        if ($mois >= 9 && $mois <= 11) return 'automne';
        return 'hiver';
    }

    /**
     * Classifie le niveau d'irrigation
     */
    private function getNiveauIrrigation(float $volumeLitres): string
    {
        if ($volumeLitres <= 0) return 'AUCUN';
        if ($volumeLitres < 5000) return 'FAIBLE';
        if ($volumeLitres < 50000) return 'MODÉRÉ';
        return 'ÉLEVÉ';
    }

    /**
     * Génère un conseil personnalisé
     */
    private function getConseilPrincipal(string $niveau, bool $stress, array $meteo): string
    {
        if ($stress) {
            return "⚠️ Alerte stress hydrique détecté ! Augmentez l'irrigation immédiatement.";
        }

        return match($niveau) {
            'AUCUN' => "✓ Pas d'irrigation nécessaire. Les précipitations suffisent.",
            'FAIBLE' => "💧 Irrigation légère recommandée. Arrosage minimal.",
            'MODÉRÉ' => "💧💧 Irrigation modérée recommandée. Arrosage régulier.",
            'ÉLEVÉ' => "⚠️ Irrigation intensive recommandée. Besoins en eau importants.",
            default => "Évaluation en cours..."
        };
    }
}

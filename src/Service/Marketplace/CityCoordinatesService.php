<?php

namespace App\Service\Marketplace;

class CityCoordinatesService
{
    /**
     * Coordonnées approximatives des principales villes et gouvernorats de Tunisie.
     *
     * @var array<string,array{lat:float,lng:float}>
     */
    private array $cities = [
        'tunis' => ['lat' => 36.8065, 'lng' => 10.1815],
        'ariana' => ['lat' => 36.8625, 'lng' => 10.1956],
        'ben arous' => ['lat' => 36.7531, 'lng' => 10.2189],
        'manouba' => ['lat' => 36.8080, 'lng' => 10.1010],
        'nabeul' => ['lat' => 36.4561, 'lng' => 10.7376],
        'zaghouan' => ['lat' => 36.4029, 'lng' => 10.1433],
        'bizerte' => ['lat' => 37.2744, 'lng' => 9.8739],
        'beja' => ['lat' => 36.7256, 'lng' => 9.1817],
        'jendouba' => ['lat' => 36.5011, 'lng' => 8.7802],
        'kef' => ['lat' => 36.1680, 'lng' => 8.7096],
        'siliana' => ['lat' => 36.0850, 'lng' => 9.3708],
        'kairouan' => ['lat' => 35.6781, 'lng' => 10.0963],
        'kasserine' => ['lat' => 35.1676, 'lng' => 8.8365],
        'sidi bouzid' => ['lat' => 35.0382, 'lng' => 9.4849],
        'sousse' => ['lat' => 35.8256, 'lng' => 10.6369],
        'monastir' => ['lat' => 35.7780, 'lng' => 10.8262],
        'mahdia' => ['lat' => 35.5047, 'lng' => 11.0622],
        'sfax' => ['lat' => 34.7406, 'lng' => 10.7603],
        'gafsa' => ['lat' => 34.4250, 'lng' => 8.7842],
        'tozeur' => ['lat' => 33.9197, 'lng' => 8.1335],
        'kebili' => ['lat' => 33.7044, 'lng' => 8.9690],
        'gabes' => ['lat' => 33.8815, 'lng' => 10.0982],
        'medenine' => ['lat' => 33.3550, 'lng' => 10.5055],
        'tataouine' => ['lat' => 32.9297, 'lng' => 10.4518],
        
        // Quelques délégations très connues
        'ezzahra' => ['lat' => 36.7444, 'lng' => 10.3086],
        'hammam lif' => ['lat' => 36.7322, 'lng' => 10.3392],
        'carthage' => ['lat' => 36.8624, 'lng' => 10.3228],
        'sidi bou said' => ['lat' => 36.8690, 'lng' => 10.3475],
        'hammamet' => ['lat' => 36.4000, 'lng' => 10.6167],
        'bardo' => ['lat' => 36.8093, 'lng' => 10.1406],
        'marsa' => ['lat' => 36.8833, 'lng' => 10.3167],
        'mourouj' => ['lat' => 36.7197, 'lng' => 10.2222],
        'kram' => ['lat' => 36.8333, 'lng' => 10.3167],
        'goulette' => ['lat' => 36.8181, 'lng' => 10.3050],
        'radès' => ['lat' => 36.7667, 'lng' => 10.2833],
        'mornag' => ['lat' => 36.6806, 'lng' => 10.2917],
        'korba' => ['lat' => 36.5667, 'lng' => 10.8667],
        'kelibia' => ['lat' => 36.8500, 'lng' => 11.1000]
    ];

    /**
     * Retourne la liste des noms de villes situées dans le rayon donné.
     *
     * @param float $lat
     * @param float $lng
     * @param float $radiusKm
     * @return array
     */
    /**
     * @return string[]
     */
    public function getCitiesWithinRadius(float $lat, float $lng, float $radiusKm): array
    {
        $validCities = [];
        foreach ($this->cities as $cityName => $coords) {
            $dist = $this->calculateDistance($lat, $lng, $coords['lat'], $coords['lng']);
            if ($dist <= $radiusKm) {
                // On stocke le nom en minuscules pour faire un tri insensible à la casse
                $validCities[] = $cityName;
            }
        }
        return $validCities;
    }

    /**
     * Calcule la distance entre deux points en utilisant la formule de Haversine.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}

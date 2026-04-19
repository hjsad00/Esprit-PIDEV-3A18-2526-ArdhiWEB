<?php

namespace App\Service;

use App\Repository\Parcelles_Cultures\ParcelleRepository;
use Doctrine\ORM\EntityManagerInterface;

class CultureAdvisorService
{
    private WeatherService $weatherService;
    private GeminiService $geminiService;
    private ParcelleRepository $parcelleRepo;
    private EntityManagerInterface $entityManager;

    public function __construct(
        WeatherService $weatherService,
        GeminiService $geminiService,
        ParcelleRepository $parcelleRepo,
        EntityManagerInterface $entityManager
    ) {
        $this->weatherService = $weatherService;
        $this->geminiService = $geminiService;
        $this->parcelleRepo = $parcelleRepo;
        $this->entityManager = $entityManager;
    }

    /**
     * Analyse complète parcelle + météo + IA
     */
    public function analyzeParcelleForCulture(int $parcelleId, string $season, ?string $cultureSaisie = null): array
    {
        // 1. Charger parcelle
        $parcelle = $this->parcelleRepo->find($parcelleId);
        if (!$parcelle) {
            return ['success' => false, 'error' => 'Parcelle non trouvée'];
        }

        // 2. Récupérer météo par coordonnées
        $latitude = $parcelle->getLatitude() ? (float)$parcelle->getLatitude() : null;
        $longitude = $parcelle->getLongitude() ? (float)$parcelle->getLongitude() : null;

        if (!$latitude || !$longitude) {
            $weather = [
                'success' => true,
                'temperature' => 25,
                'humidity' => 60,
                'rain' => 0,
                'wind_speed' => 5,
                'description' => 'Météo estimée',
                'icon' => '01d',
                'clouds' => 50,
                'feels_like' => 25
            ];
        } else {
            $weather = $this->weatherService->getCurrentWeather($latitude, $longitude);
            if (!$weather['success']) {
                $weather = [
                    'success' => true,
                    'temperature' => 25,
                    'humidity' => 60,
                    'rain' => 0,
                    'wind_speed' => 5,
                    'description' => 'Fallback',
                    'icon' => '01d',
                    'clouds' => 50,
                    'feels_like' => 25
                ];
            }
        }

        // 3. Analyser météo
        $weatherAnalysis = $this->weatherService->analyzeWeatherForCulture($weather, $season);

        // 4. Calculer surface restante
        $totalSurface = (float)$parcelle->getSurface();
        $usedSurface = 0.0;
        foreach ($parcelle->getCultures() as $culture) {
            $usedSurface += (float)$culture->getSurfaceUtilisee();
        }
        $remainingSurface = $totalSurface - $usedSurface;

        // 5. Appeler Gemini pour vraie recommandation IA
        error_log('🤖 Lancement Google Gemini AI...');
        $geminiContext = [
            'culture_saisie' => $cultureSaisie ?? '',
            'soil_type' => $parcelle->getTypeSol() ?? 'Non spécifié',
            'temperature' => $weather['temperature'] ?? 25,
            'humidity' => $weather['humidity'] ?? 60,
            'season' => $season,
            'region' => $parcelle->getLocalisation() ?? 'Non spécifiée',
            'remaining_surface' => $remainingSurface
        ];

        // ON APPELLE L'IA DIRECTEMENT
        $geminiResponse = $this->geminiService->recommendCulture($geminiContext);
        error_log('🤖 Résultat Google Gemini: ' . ($geminiResponse['success'] ? 'SUCCÈS' : 'ÉCHEC - ' . ($geminiResponse['error'] ?? 'Inconnu')));

        if (!$geminiResponse['success']) {
            return [
                'success' => false,
                'error' => 'Erreur API IA: ' . ($geminiResponse['error'] ?? 'Erreur inconnue. Vérifiez votre clé API ou votre connexion.')
            ];
        }

        // 6. Compiler réponse finale
        $recommendation = $geminiResponse['recommendation'];

        return [
            'success' => true,
            'parcelle' => [
                'id' => $parcelle->getId(),
                'name' => $parcelle->getLocalisation(),
                'soil_type' => $parcelle->getTypeSol(),
                'surface' => $totalSurface,
                'used_surface' => $usedSurface,
                'remaining_surface' => $remainingSurface,
                'occupation_percent' => $totalSurface > 0 ? round(($usedSurface / $totalSurface) * 100) : 0,
                'localisation' => $parcelle->getLocalisation(),
                'latitude' => $parcelle->getLatitude(),
                'longitude' => $parcelle->getLongitude()
            ],
            'weather' => $weather,
            'weather_analysis' => $weatherAnalysis,
            'recommendation' => $recommendation,
            'season' => $season,
            'culture_saisie' => $cultureSaisie
        ];
    }

    /**
     * Sauvegarde suggestion IA en historique
     */
    public function saveSuggestion(int $parcelleId, array $analysis, bool $accepted = false): void
    {
        try {
            $recommendation = $analysis['recommendation'] ?? [];

            $data = [
                'parcelle_id' => $parcelleId,
                'culture_principale' => $recommendation['principal'] ?? '',
                'alternatives' => $recommendation['alternatives'] ?? [],
                'justification' => $recommendation['justification'] ?? '',
                'meteo' => [
                    'temperature' => $analysis['weather']['temperature'] ?? null,
                    'humidity' => $analysis['weather']['humidity'] ?? null,
                    'description' => $analysis['weather']['description'] ?? ''
                ],
                'saison' => $analysis['season'] ?? '',
                'accepted' => $accepted,
                'created_at' => new \DateTime()
            ];

            // Insérer direct en BD via requête SQL
            $sql = "INSERT INTO ai_suggestions (parcelle_id, culture_principale, alternatives, justification, meteo, saison, accepted, created_at) 
                    VALUES (:parcelle_id, :culture, :alternatives, :justification, :meteo, :saison, :accepted, :created_at)";

            $conn = $this->entityManager->getConnection();
            $conn->executeStatement($sql, [
                'parcelle_id' => $parcelleId,
                'culture' => $data['culture_principale'],
                'alternatives' => json_encode($data['alternatives']),
                'justification' => $data['justification'],
                'meteo' => json_encode($data['meteo']),
                'saison' => $data['saison'],
                'accepted' => $accepted ? 1 : 0,
                'created_at' => $data['created_at']->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas le flux
            error_log('Erreur sauvegarde suggestion: ' . $e->getMessage());
        }
    }

    /**
     * Calcule occupation surface avec couleur
     */
    public function calculateSurfaceOccupation(float $used, float $total): array
    {
        if ($total == 0) {
            return [
                'percent' => 0,
                'remaining' => 0,
                'status' => 'empty'
            ];
        }

        $percent = round(($used / $total) * 100);

        return [
            'percent' => $percent,
            'remaining' => $total - $used,
            'status' => $percent < 70 ? 'ok' : ($percent < 90 ? 'warning' : 'full')
        ];
    }
}

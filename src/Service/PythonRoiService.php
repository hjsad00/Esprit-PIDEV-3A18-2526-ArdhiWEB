<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Psr\Log\LoggerInterface;

class PythonRoiService
{
    private string $pythonPath;
    private string $roiScriptPath;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        // Détecter l'OS pour le chemin Python
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Priorité au chemin Anaconda détecté
            $anacondaPath = 'C:\\Users\\delho\\anaconda3\\python.exe';
            $this->pythonPath = file_exists($anacondaPath) ? $anacondaPath : 'python';
        } else {
            $this->pythonPath = 'python3';
        }
        
        // Chemin du script
        $path = __DIR__ . '/../../python/roi_engine.py';
        $this->roiScriptPath = realpath($path) ?: $path;
    }

    /**
     * Analyse la rentabilité agricole via le moteur Python
     *
     * @param array $data Données du formulaire
     * @return array Résultat JSON du moteur Python
     */
    public function analyzeROI(array $data): array
    {
        try {
            $this->logger->info('🚀 Lancement moteur ROI Python...', ['data' => $data]);

            // Préparer les données JSON
            $jsonInput = json_encode($data, JSON_UNESCAPED_UNICODE);

            // Créer le processus Python
            $process = new Process([$this->pythonPath, $this->roiScriptPath]);
            $process->setInput($jsonInput);
            $process->setTimeout(10); 

            // Exécuter
            $process->run();

            // Vérifier les erreurs
            if (!$process->isSuccessful()) {
                $errorMsg = $process->getErrorOutput() ?: $process->getOutput();
                $this->logger->error('❌ Erreur Python', [
                    'stderr' => $process->getErrorOutput(),
                    'stdout' => $process->getOutput(),
                    'command' => $process->getCommandLine()
                ]);

                return $this->getFallbackROI($data, "Erreur Python: " . substr($errorMsg, 0, 100));
            }

            // Parser le résultat JSON
            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('❌ Erreur parsing JSON', ['output' => $output]);
                return $this->getFallbackROI($data, "Erreur de format JSON");
            }

            $this->logger->info('✅ Analyse ROI complétée', ['result' => $result]);
            return $result;

        } catch (ProcessFailedException $e) {
            $this->logger->error('❌ Exception Process', ['error' => $e->getMessage()]);
            return $this->getFallbackROI($data, "Erreur Processus");
        } catch (\Exception $e) {
            $this->logger->error('❌ Exception générale', ['error' => $e->getMessage()]);
            return $this->getFallbackROI($data, "Exception: " . $e->getMessage());
        }
    }

    /**
     * Fallback: Calcul simple en PHP si Python échoue
     *
     * @param array $data
     * @return array
     */
    private function getFallbackROI(array $data, string $errorReason = null): array
    {
        $this->logger->warning('⚠️ Utilisation du fallback PHP', ['reason' => $errorReason]);

        // Calcul simple en PHP
        $cout_total = ($data['cout_semences'] ?? 0) +
                      ($data['cout_engrais'] ?? 0) +
                      ($data['cout_main_oeuvre'] ?? 0) +
                      ($data['cout_irrigation'] ?? 0) +
                      ($data['autres_couts'] ?? 0);

        $facteur_clim = 1.0 - (($data['jours_canicule'] ?? 0) * 0.03) -
                        (($data['jours_pluie'] ?? 0) * 0.02) -
                        (($data['jours_gel'] ?? 0) * 0.04);
        $facteur_clim = max(0.50, min(1.20, $facteur_clim));

        $production = (($data['surface'] ?? 0) * ($data['rendement'] ?? 0)) * $facteur_clim;
        $revenu = $production * ($data['prix_vente'] ?? 0);
        $marge = $revenu - $cout_total;
        $roi = $cout_total > 0 ? ($marge / $cout_total * 100) : 0;

        return [
            'production' => round($production, 2),
            'revenu' => round($revenu, 2),
            'cout_total' => round($cout_total, 2),
            'marge' => round($marge, 2),
            'roi' => round($roi, 2),
            'capacite_pret' => round($marge * 0.60, 2),
            'facteur_climatique' => round($facteur_clim, 3),
            'niveau' => $roi > 40 ? 'Très rentable' : ($roi > 20 ? 'Rentable' : ($roi > 0 ? 'Moyen' : 'Risque élevé')),
            'emoji' => $roi > 40 ? '🔥' : ($roi > 20 ? '🟢' : ($roi > 0 ? '🟡' : '🔴')),
            'risque' => $facteur_clim < 0.60 ? 'Élevé' : ($facteur_clim < 0.80 ? 'Modéré' : 'Faible'),
            'conseils' => ['⚠️ Calcul en mode fallback', $errorReason ?: 'Python indisponible'],
            'alternative' => 'Calcul simplifié',
            'success' => true,
            'fallback' => true,
        ];
    }
}

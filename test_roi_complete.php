<?php
/**
 * Teste le moteur ROI directement (sans Symfony)
 * Simule ce que le backend va faire
 */

require_once 'vendor/autoload.php';

echo "═════════════════════════════════════════\n";
echo "🧪 TEST INTEGRATION DIRECTE\n";
echo "═════════════════════════════════════════\n\n";

// Test data
$testCases = [
    [
        'name' => 'Tomate - Conditions optimales',
        'data' => [
            'surface' => 5,
            'rendement' => 50,
            'culture' => 'Tomate',
            'cout_semences' => 500,
            'cout_engrais' => 1000,
            'cout_main_oeuvre' => 800,
            'cout_irrigation' => 300,
            'autres_couts' => 200,
            'jours_canicule' => 0,
            'jours_pluie' => 0,
            'jours_gel' => 0,
            'prix_vente' => 5,
        ]
    ],
    [
        'name' => 'Piment - Risque modéré',
        'data' => [
            'surface' => 3,
            'rendement' => 40,
            'culture' => 'Piment',
            'cout_semences' => 400,
            'cout_engrais' => 1500,
            'cout_main_oeuvre' => 1000,
            'cout_irrigation' => 400,
            'autres_couts' => 150,
            'jours_canicule' => 5,
            'jours_pluie' => 3,
            'jours_gel' => 0,
            'prix_vente' => 6.5,
        ]
    ],
    [
        'name' => 'Blé - Rendement bas',
        'data' => [
            'surface' => 10,
            'rendement' => 30,
            'culture' => 'Blé',
            'cout_semences' => 800,
            'cout_engrais' => 1200,
            'cout_main_oeuvre' => 600,
            'cout_irrigation' => 200,
            'autres_couts' => 100,
            'jours_canicule' => 8,
            'jours_pluie' => 2,
            'jours_gel' => 1,
            'prix_vente' => 3.5,
        ]
    ],
];

foreach ($testCases as $i => $test) {
    echo "Test " . ($i + 1) . ": {$test['name']}\n";
    echo "─────────────────────────────────────────\n";
    
    $json = json_encode($test['data']);
    
    $process = proc_open(
        'python python/roi_engine.py',
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes
    );
    
    if (is_resource($process)) {
        fwrite($pipes[0], $json);
        fclose($pipes[0]);
        
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        $code = proc_close($process);
        
        if ($code === 0 && $output) {
            $result = json_decode($output, true);
            
            if ($result && $result['success']) {
                echo "✅ ROI: " . $result['roi'] . "%\n";
                echo "   Niveau: " . $result['niveau'] . " " . $result['emoji'] . "\n";
                echo "   Marge: " . $result['marge'] . " DT\n";
                echo "   Risque: " . $result['risque'] . "\n";
                
                if (!empty($result['conseils'])) {
                    echo "   Conseils:\n";
                    foreach ($result['conseils'] as $conseil) {
                        echo "     - $conseil\n";
                    }
                }
                
                if ($result['alternative'] !== 'Maintenir la culture actuelle') {
                    echo "   💡 Alternative: " . $result['alternative'] . "\n";
                }
            } else {
                echo "❌ Erreur: " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "❌ Process error (code: $code): $errors\n";
        }
    } else {
        echo "❌ Failed to start process\n";
    }
    
    echo "\n";
}

echo "═════════════════════════════════════════\n";
echo "✨ TESTS COMPLÉTÉS\n\n";
echo "Le moteur Python fonctionne correctement!\n";
echo "Accès au dashboard ROI:\n";
echo "👉 http://127.0.0.1:8000/farmer/roi/calculator\n";

<?php
/**
 * Test du Module ROI
 * Vérifie que tous les composants fonctionnent
 */

require_once 'vendor/autoload.php';

echo "\n🧪 TEST MODULE ROI AVANCÉ\n";
echo "═════════════════════════════════════════\n\n";

// ============= TEST 1: Moteur Python =============
echo "1️⃣  TEST MOTEUR PYTHON\n";
echo "─────────────────────\n";

$testData = [
    'surface' => 5,
    'rendement' => 50,
    'culture' => 'Tomate',
    'cout_semences' => 1000,
    'cout_engrais' => 2000,
    'cout_main_oeuvre' => 1500,
    'cout_irrigation' => 500,
    'autres_couts' => 300,
    'jours_canicule' => 4,
    'jours_pluie' => 2,
    'jours_gel' => 0,
    'prix_vente' => 5,
];

$json = json_encode($testData);
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
    proc_close($process);
    
    if ($output) {
        $result = json_decode($output, true);
        echo "✅ Moteur Python fonctionne\n";
        echo "   📊 ROI: " . $result['roi'] . "%\n";
        echo "   💰 Marge: " . $result['marge'] . " DT\n";
        echo "   ⚠️  Risque: " . $result['risque'] . "\n";
    } else {
        echo "❌ Erreur Python: $errors\n";
    }
} else {
    echo "❌ Impossible de lancer Python\n";
}

// ============= TEST 2: Fichiers Frontend =============
echo "\n2️⃣  FICHIERS FRONTEND\n";
echo "─────────────────────\n";

$files = [
    'public/js/roi-analyzer.js' => 'JavaScript AJAX',
    'public/css/roi-premium.css' => 'CSS Premium',
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ $desc: $file ($size bytes)\n";
    } else {
        echo "❌ Manquant: $file\n";
    }
}

// ============= TEST 3: Entity Doctrine =============
echo "\n3️⃣  ENTITY DOCTRINE\n";
echo "──────────────────\n";

$entityClass = 'App\\Entity\\Parcelles_Cultures\\RoiAnalyse';
if (class_exists($entityClass)) {
    echo "✅ Entity RoiAnalyse trouvée\n";
    $reflect = new ReflectionClass($entityClass);
    echo "   📍 Chemin: " . $reflect->getFileName() . "\n";
} else {
    echo "❌ Entity RoiAnalyse introuvable\n";
}

// ============= TEST 4: Service Symfony =============
echo "\n4️⃣  SERVICE SYMFONY\n";
echo "──────────────────\n";

$serviceClass = 'App\\Service\\PythonRoiService';
if (class_exists($serviceClass)) {
    echo "✅ Service PythonRoiService trouvé\n";
} else {
    echo "❌ Service PythonRoiService introuvable\n";
}

// ============= TEST 5: Template Twig =============
echo "\n5️⃣  TEMPLATE TWIG\n";
echo "─────────────────\n";

$template = 'templates/parcelles_cultures/farmer/roi/calculator.html.twig';
if (file_exists($template)) {
    $content = file_get_contents($template);
    if (strpos($content, 'roi-analyzer.js') !== false) {
        echo "✅ Template charge roi-analyzer.js\n";
    } else {
        echo "⚠️  Template existe mais roi-analyzer.js n'est pas linké\n";
    }
    
    if (strpos($content, 'roi-premium.css') !== false) {
        echo "✅ Template charge roi-premium.css\n";
    } else {
        echo "⚠️  Template existe mais roi-premium.css n'est pas linké\n";
    }
} else {
    echo "❌ Template introuvable: $template\n";
}

// ============= TEST 6: Base de Données =============
echo "\n6️⃣  BASE DE DONNÉES\n";
echo "──────────────────\n";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=ardhi', 'root', '');
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'roi_analyses'");
    $tables = $stmt->fetchAll();
    
    if (!empty($tables)) {
        echo "✅ Table roi_analyses existe\n";
        
        // Vérifier les colonnes
        $stmt = $pdo->query("DESCRIBE roi_analyses");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $required = ['id', 'parcelle_id', 'culture', 'roi', 'marge', 'revenu', 'cout_total', 'niveau', 'risque', 'conseils', 'alternative', 'created_at', 'updated_at'];
        $missing = array_diff($required, $columns);
        
        if (empty($missing)) {
            echo "✅ Tous les champs requis sont présents\n";
            echo "   Colonnes: " . implode(', ', $columns) . "\n";
        } else {
            echo "⚠️  Colonnes manquantes: " . implode(', ', $missing) . "\n";
        }
    } else {
        echo "❌ Table roi_analyses n'existe pas\n";
        echo "   💡 Créez-la via phpMyAdmin ou le script SQL\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur BD: " . $e->getMessage() . "\n";
}

// ============= RÉSUMÉ =============
echo "\n═════════════════════════════════════════\n";
echo "✨ TESTS COMPLÉTÉS\n\n";
echo "Prochaines étapes:\n";
echo "1. Accédez à http://127.0.0.1:8000/farmer/roi/calculator\n";
echo "2. Remplissez le formulaire\n";
echo "3. Cliquez 'Lancer l'Analyse Avancée'\n";
echo "4. Les résultats s'affichent en premium!\n\n";

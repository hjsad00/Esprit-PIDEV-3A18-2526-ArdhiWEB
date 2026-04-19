# =============================================================================
# Script d'Installation du Module ROI Avancé (Windows PowerShell)
# Ce script configure complètement le module ROI Symfony + Python
# =============================================================================

Write-Host "`n" -ForegroundColor Green
Write-Host "╔══════════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║      🚀 Installation Module ROI Avancé (Symfony + Python)            ║" -ForegroundColor Cyan
Write-Host "║         Système Hybride d'Analyse Financière Agricole               ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host "`n" -ForegroundColor Green

# Variables
$script:STEP = 0
$script:ERRORS = 0
$projectRoot = Get-Location

function PrintStep {
    param([string]$message)
    $script:STEP += 1
    Write-Host "`n[Étape $($script:STEP)] $message" -ForegroundColor Blue
}

function PrintSuccess {
    param([string]$message)
    Write-Host "✅ $message" -ForegroundColor Green
}

function PrintError {
    param([string]$message)
    Write-Host "❌ $message" -ForegroundColor Red
    $script:ERRORS += 1
}

function PrintWarning {
    param([string]$message)
    Write-Host "⚠️  $message" -ForegroundColor Yellow
}

# =============================================================================
# ÉTAPE 1: Vérifier Python 3
# =============================================================================
PrintStep "Vérifier Python 3 est installé"

$pythonCmd = Get-Command python3 -ErrorAction SilentlyContinue

if ($null -eq $pythonCmd) {
    $pythonCmd = Get-Command python -ErrorAction SilentlyContinue
    if ($null -eq $pythonCmd) {
        PrintError "Python n'est pas installé ou pas dans le PATH"
        PrintWarning "Installez Python 3 depuis: https://www.python.org/downloads/"
    } else {
        $version = & python --version
        PrintSuccess "Trouvé: $version"
    }
} else {
    $version = & python3 --version
    PrintSuccess "Trouvé: $version"
}

# =============================================================================
# ÉTAPE 2: Vérifier PHP
# =============================================================================
PrintStep "Vérifier PHP est installé"

$phpCmd = Get-Command php -ErrorAction SilentlyContinue

if ($null -eq $phpCmd) {
    PrintError "PHP n'est pas installé ou pas dans le PATH"
} else {
    $version = & php --version | Select-Object -First 1
    PrintSuccess "Trouvé: $version"
}

# =============================================================================
# ÉTAPE 3: Vider le cache Symfony
# =============================================================================
PrintStep "Vider le cache Symfony"

$consoleExists = Test-Path "bin\console"

if ($consoleExists) {
    & php bin/console cache:clear --env=dev 2>$null | Out-Null
    PrintSuccess "Cache Symfony vidé"
} else {
    PrintError "bin/console introuvable - assurez-vous d'être à la racine du projet"
}

# =============================================================================
# ÉTAPE 4: Vérifier les fichiers existants
# =============================================================================
PrintStep "Vérifier les fichiers du module ROI"

$files = @(
    "python\roi_engine.py"
    "src\Service\PythonRoiService.php"
    "src\Entity\Parcelles_Cultures\RoiAnalyse.php"
    "src\Repository\Parcelles_Cultures\RoiAnalyseRepository.php"
    "src\Controller\Parcelles_Cultures\Farmer\RoiController.php"
    "public\js\roi-analyzer.js"
    "public\css\roi-premium.css"
    "templates\parcelles_cultures\farmer\roi\calculator.html.twig"
)

foreach ($file in $files) {
    $filePath = Join-Path $projectRoot $file
    if (Test-Path $filePath) {
        PrintSuccess "Trouvé: $file"
    } else {
        PrintError "Manquant: $file"
    }
}

# =============================================================================
# ÉTAPE 5: Test Python
# =============================================================================
PrintStep "Tester le moteur Python"

$pythonFile = Join-Path $projectRoot "python\roi_engine.py"

if (Test-Path $pythonFile) {
    Write-Host "📝 Envoi des données de test au moteur Python..."
    
    $testJson = @{
        surface = 5
        rendement = 50
        jours_canicule = 4
        jours_pluie = 2
        jours_gel = 0
        cout_semences = 1000
        cout_engrais = 2000
        cout_main_oeuvre = 1500
        cout_irrigation = 500
        autres_couts = 300
        prix_vente = 5
        culture = "Tomate"
    } | ConvertTo-Json
    
    $pythonExe = if ($null -ne $pythonCmd) { "python3" } else { "python" }
    
    try {
        $output = $testJson | & $pythonExe $pythonFile 2>&1
        PrintSuccess "Moteur Python fonctionne correctement"
        Write-Host "   Résultat: $($output.Substring(0, [Math]::Min(100, $output.Length)))..."
    } catch {
        PrintError "Erreur lors de l'exécution du moteur Python"
        Write-Host "   Erreur: $_"
    }
} else {
    PrintError "Fichier python\roi_engine.py introuvable"
}

# =============================================================================
# ÉTAPE 6: Instructions base de données
# =============================================================================
PrintStep "Configuration de la base de données"

Write-Host "`n📚 Instructions pour créer la table roi_analyses:`n" -ForegroundColor White

Write-Host "  1. Ouvrez phpMyAdmin"
Write-Host "  2. Sélectionnez votre base de données"
Write-Host "  3. Allez à l'onglet 'SQL'"
Write-Host "  4. Collez le SQL suivant:`n"

Write-Host @"
CREATE TABLE IF NOT EXISTS roi_analyses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parcelle_id INT,
  culture VARCHAR(100) NOT NULL,
  roi DECIMAL(10, 2) NOT NULL,
  marge DECIMAL(10, 2) NOT NULL,
  revenu DECIMAL(10, 2) NOT NULL,
  cout_total DECIMAL(10, 2) NOT NULL,
  niveau VARCHAR(50) NOT NULL,
  risque VARCHAR(50) NOT NULL,
  conseils JSON,
  alternative VARCHAR(200),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parcelle_id) REFERENCES parcelle(id) ON DELETE CASCADE,
  INDEX idx_parcelle (parcelle_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"@ -ForegroundColor White

Write-Host "`n" -ForegroundColor White

# =============================================================================
# ÉTAPE 7: Permissions fichiers
# =============================================================================
PrintStep "Vérifier les permissions des fichiers"

$logDir = Join-Path $projectRoot "var\log"

if (Test-Path $logDir) {
    PrintSuccess "Dossier var\log existe"
} else {
    PrintWarning "Dossier var\log n'existe pas - Symfony le créera automatiquement"
}

# =============================================================================
# ÉTAPE 8: Vérifier la configuration
# =============================================================================
PrintStep "Vérifier la configuration Symfony"

$servicesFile = Join-Path $projectRoot "config\services.yaml"

if (Test-Path $servicesFile) {
    $content = Get-Content $servicesFile -Raw
    if ($content -match "PythonRoiService") {
        PrintSuccess "PythonRoiService est déclaré dans services.yaml"
    } else {
        PrintWarning "Assurez-vous que PythonRoiService est déclaré dans config\services.yaml"
    }
}

# =============================================================================
# ÉTAPE 9: Test endpoint
# =============================================================================
PrintStep "Tester l'endpoint /farmer/roi/analyze"

Write-Host "`n  Pour tester manuellement l'endpoint:"
Write-Host "`n  curl -X POST http://localhost:8000/farmer/roi/analyze \" -ForegroundColor White
Write-Host "    -H 'Content-Type: application/json' \" -ForegroundColor White
Write-Host "    -d '{`"surface`": 5, `"rendement`": 50, `"culture`": `"Tomate`"}'" -ForegroundColor White
Write-Host "`n" -ForegroundColor White

# =============================================================================
# RÉSUMÉ
# =============================================================================
PrintStep "Résumé de l'installation"

Write-Host "`n" -ForegroundColor Green

if ($script:ERRORS -eq 0) {
    Write-Host "✅ Installation complète !" -ForegroundColor Green
    Write-Host "`nProchaines étapes:" -ForegroundColor Green
    Write-Host "  1. Créez la table roi_analyses via phpMyAdmin (voir étape 6)" -ForegroundColor White
    Write-Host "  2. Videz le cache: php bin/console cache:clear" -ForegroundColor White
    Write-Host "  3. Lancez Symfony: symfony server:start (ou php -S localhost:8000)" -ForegroundColor White
    Write-Host "  4. Visitez: http://localhost:8000/farmer/roi/calculator" -ForegroundColor White
    Write-Host "`n📚 Documentation:" -ForegroundColor Green
    Write-Host "  → Lire ROI_MODULE_GUIDE.md pour plus de détails" -ForegroundColor White
} else {
    Write-Host "⚠️  Installation terminée avec $($script:ERRORS) erreur(s)" -ForegroundColor Red
    Write-Host "`nErreurs détectées:" -ForegroundColor Red
    Write-Host "  → Vérifiez les messages ci-dessus" -ForegroundColor White
    Write-Host "  → Consultez ROI_MODULE_GUIDE.md pour le dépannage" -ForegroundColor White
}

Write-Host "`n═════════════════════════════════════════════════════════" -ForegroundColor Blue
Write-Host "`n" -ForegroundColor Green

exit $script:ERRORS

#!/usr/bin/env bash
# =============================================================================
# Script d'Installation du Module ROI Avancé
# Ce script configure complètement le module ROI Symfony + Python
# =============================================================================

echo ""
echo "╔══════════════════════════════════════════════════════════════════════╗"
echo "║      🚀 Installation Module ROI Avancé (Symfony + Python)            ║"
echo "║         Système Hybride d'Analyse Financière Agricole               ║"
echo "╚══════════════════════════════════════════════════════════════════════╝"
echo ""

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
STEP=0
ERRORS=0

# Fonction pour afficher les étapes
step() {
    STEP=$((STEP + 1))
    echo ""
    echo -e "${BLUE}[Étape $STEP]${NC} $1"
}

# Fonction pour succès
success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Fonction pour erreur
error() {
    echo -e "${RED}❌ $1${NC}"
    ERRORS=$((ERRORS + 1))
}

# Fonction pour avertissement
warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

# =============================================================================
# ÉTAPE 1: Vérifier Python 3
# =============================================================================
step "Vérifier Python 3 est installé"

if ! command -v python3 &> /dev/null; then
    error "Python 3 n'est pas installé ou pas dans le PATH"
    warning "Installez Python 3 ou assurez-vous qu'il est dans le PATH"
    echo "   → Téléchargez depuis: https://www.python.org/downloads/"
else
    VERSION=$(python3 --version)
    success "Trouvé: $VERSION"
fi

# =============================================================================
# ÉTAPE 2: Vérifier Symfony
# =============================================================================
step "Vérifier Symfony CLI"

if ! command -v symfony &> /dev/null; then
    warning "Symfony CLI n'est pas installé (optionnel mais recommandé)"
    echo "   → Installation: https://symfony.com/download"
else
    SFVER=$(symfony -V)
    success "Trouvé: $SFVER"
fi

# =============================================================================
# ÉTAPE 3: Vider le cache Symfony
# =============================================================================
step "Vider le cache Symfony"

if [ -f "bin/console" ]; then
    php bin/console cache:clear --env=dev 2>/dev/null
    success "Cache Symfony vidé"
else
    error "bin/console introuvable - assurez-vous d'être à la racine du projet"
fi

# =============================================================================
# ÉTAPE 4: Vérifier les fichiers existants
# =============================================================================
step "Vérifier les fichiers du module ROI"

FILES=(
    "python/roi_engine.py"
    "src/Service/PythonRoiService.php"
    "src/Entity/Parcelles_Cultures/RoiAnalyse.php"
    "src/Repository/Parcelles_Cultures/RoiAnalyseRepository.php"
    "src/Controller/Parcelles_Cultures/Farmer/RoiController.php"
    "public/js/roi-analyzer.js"
    "public/css/roi-premium.css"
    "templates/parcelles_cultures/farmer/roi/calculator.html.twig"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        success "Trouvé: $file"
    else
        error "Manquant: $file"
    fi
done

# =============================================================================
# ÉTAPE 5: Test Python
# =============================================================================
step "Tester le moteur Python"

if [ -f "python/roi_engine.py" ]; then
    echo "📝 Envoi des données de test au moteur Python..."
    
    TEST_JSON='{"surface": 5, "rendement": 50, "jours_canicule": 4, "jours_pluie": 2, "jours_gel": 0, "cout_semences": 1000, "cout_engrais": 2000, "cout_main_oeuvre": 1500, "cout_irrigation": 500, "autres_couts": 300, "prix_vente": 5, "culture": "Tomate"}'
    
    PYTHON_OUTPUT=$(echo "$TEST_JSON" | python3 python/roi_engine.py 2>&1)
    PYTHON_EXIT=$?
    
    if [ $PYTHON_EXIT -eq 0 ]; then
        success "Moteur Python fonctionne correctement"
        echo "   Résultat: $(echo "$PYTHON_OUTPUT" | head -c 100)..."
    else
        error "Erreur lors de l'exécution du moteur Python"
        echo "   Erreur: $PYTHON_OUTPUT"
    fi
else
    error "Fichier python/roi_engine.py introuvable"
fi

# =============================================================================
# ÉTAPE 6: Instructions base de données
# =============================================================================
step "Configuration de la base de données"

echo ""
echo "📚 Instructions pour créer la table roi_analyses:"
echo ""
echo "  1. Ouvrez phpMyAdmin"
echo "  2. Sélectionnez votre base de données"
echo "  3. Allez à l'onglet 'SQL'"
echo "  4. Collez le SQL suivant:"
echo ""
cat << 'EOF'
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
EOF
echo ""

# =============================================================================
# ÉTAPE 7: Permissions fichiers
# =============================================================================
step "Vérifier les permissions des fichiers"

if [ -d "var/log" ]; then
    if [ -w "var/log" ]; then
        success "Dossier var/log est accessible en écriture"
    else
        warning "Dossier var/log n'est pas accessible en écriture"
        echo "   Exécutez: chmod -R 777 var/"
    fi
else
    warning "Dossier var/log n'existe pas"
fi

# =============================================================================
# ÉTAPE 8: Vérifier la configuration
# =============================================================================
step "Vérifier la configuration Symfony"

if grep -q "PythonRoiService" "config/services.yaml" 2>/dev/null; then
    success "PythonRoiService est déclaré dans services.yaml"
else
    warning "Assurez-vous que PythonRoiService est déclaré dans config/services.yaml"
fi

# =============================================================================
# ÉTAPE 9: Test endpoint
# =============================================================================
step "Tester l'endpoint /farmer/roi/analyze"

echo ""
echo "  Pour tester manuellement l'endpoint:"
echo ""
echo "  curl -X POST http://localhost:8000/farmer/roi/analyze \\"
echo "    -H 'Content-Type: application/json' \\"
echo "    -d '{\"surface\": 5, \"rendement\": 50, \"culture\": \"Tomate\"}'"
echo ""

# =============================================================================
# RÉSUMÉ
# =============================================================================
step "Résumé de l'installation"

echo ""
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ Installation complète !${NC}"
    echo ""
    echo "Prochaines étapes:"
    echo "  1. Créez la table roi_analyses via phpMyAdmin (voir étape 6)"
    echo "  2. Videz le cache: php bin/console cache:clear"
    echo "  3. Lancez Symfony: symfony server:start"
    echo "  4. Visitez: http://localhost:8000/farmer/roi/calculator"
    echo ""
    echo "📚 Documentation:"
    echo "  → Lire ROI_MODULE_GUIDE.md pour plus de détails"
    echo ""
else
    echo -e "${RED}⚠️  Installation terminée avec $ERRORS erreur(s)${NC}"
    echo ""
    echo "Erreurs détectées:"
    echo "  → Vérifiez les messages ci-dessus"
    echo "  → Consultez ROI_MODULE_GUIDE.md pour le dépannage"
fi

echo ""
echo -e "${BLUE}═════════════════════════════════════════════════════════${NC}"
echo ""

exit $ERRORS

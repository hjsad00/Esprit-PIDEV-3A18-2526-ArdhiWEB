<?php
require_once 'vendor/autoload.php';

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=ardhi', 'root', '');
    
    $sql = 'CREATE TABLE IF NOT EXISTS roi_analyses (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    
    $pdo->exec($sql);
    echo "✅ Table roi_analyses créée avec succès\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

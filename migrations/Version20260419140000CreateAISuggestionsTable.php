<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419140000CreateAISuggestionsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ai_suggestions table for tracking IA recommendations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_suggestions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parcelle_id INT NOT NULL,
            culture_principale VARCHAR(100),
            alternatives JSON,
            justification TEXT,
            meteo JSON,
            saison VARCHAR(50),
            accepted TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parcelle_id) REFERENCES farmer_parcelles(id) ON DELETE CASCADE,
            INDEX idx_parcelle (parcelle_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ai_suggestions');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table roi_analyses
 * Stocke l'historique des analyses ROI
 */
final class Version20260419000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create roi_analyses table for ROI analysis history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS roi_analyses (
            id INT AUTO_INCREMENT NOT NULL,
            parcelle_id INT,
            culture VARCHAR(100) NOT NULL,
            roi NUMERIC(10, 2) NOT NULL,
            marge NUMERIC(10, 2) NOT NULL,
            revenu NUMERIC(10, 2) NOT NULL,
            cout_total NUMERIC(10, 2) NOT NULL,
            niveau VARCHAR(50) NOT NULL,
            risque VARCHAR(50) NOT NULL,
            conseils JSON,
            alternative VARCHAR(200),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            INDEX idx_parcelle (parcelle_id),
            INDEX idx_created (created_at),
            FOREIGN KEY (parcelle_id) REFERENCES parcelle(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS roi_analyses');
    }
}

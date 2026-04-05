<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Parcelle, Culture, IrrigationRequest, and CreditDossier tables for Parcelles_Cultures module';
    }

    public function up(Schema $schema): void
    {
        // Parcelle table
        $this->addSql('CREATE TABLE IF NOT EXISTS parcelle (
            id INT AUTO_INCREMENT NOT NULL,
            agriculteur_id INT NOT NULL,
            surface NUMERIC(10, 2) NOT NULL,
            localisation VARCHAR(255) NOT NULL,
            type_sol VARCHAR(100) NOT NULL,
            systeme_irrigation VARCHAR(100) NOT NULL,
            statut VARCHAR(50) NOT NULL DEFAULT "active",
            latitude NUMERIC(10, 6),
            longitude NUMERIC(10, 6),
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            PRIMARY KEY (id),
            KEY IDX_FFB53A2A44C7A413 (agriculteur_id),
            CONSTRAINT FK_FFB53A2A44C7A413 FOREIGN KEY (agriculteur_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Culture table
        $this->addSql('CREATE TABLE IF NOT EXISTS culture (
            id INT AUTO_INCREMENT NOT NULL,
            parcelle_id INT NOT NULL,
            nom_culture VARCHAR(255) NOT NULL,
            type_culture VARCHAR(100) NOT NULL,
            saison VARCHAR(50) NOT NULL,
            date_plantation DATE NOT NULL,
            date_recolte_prevue DATE NOT NULL,
            etat_culture VARCHAR(50) NOT NULL DEFAULT "active",
            surface_utilisee NUMERIC(10, 2) NOT NULL,
            rendement_estime NUMERIC(10, 2) NOT NULL,
            production_estimee NUMERIC(10, 2),
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            PRIMARY KEY (id),
            KEY IDX_C3E08B7B4A3FF (parcelle_id),
            CONSTRAINT FK_C3E08B7BC3E08B7B FOREIGN KEY (parcelle_id) REFERENCES parcelle (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // IrrigationRequest table
        $this->addSql('CREATE TABLE IF NOT EXISTS irrigation_request (
            id INT AUTO_INCREMENT NOT NULL,
            parcelle_id INT NOT NULL,
            date DATE NOT NULL,
            temperature_moyenne NUMERIC(10, 2) NOT NULL,
            temperature_max NUMERIC(10, 2) NOT NULL,
            temperature_min NUMERIC(10, 2) NOT NULL,
            precipitations NUMERIC(10, 2) NOT NULL,
            humidite NUMERIC(5, 2) NOT NULL,
            kc NUMERIC(5, 2) NOT NULL,
            volume_litres NUMERIC(15, 2),
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY IDX_44C7F2C4A3FF (parcelle_id),
            CONSTRAINT FK_44C7F2C44A3FF FOREIGN KEY (parcelle_id) REFERENCES parcelle (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Update CreditDossier if needed - drop old constraints first
        $this->addSql('ALTER TABLE credit_dossier DROP FOREIGN KEY IF EXISTS idx_credit_parcelle');
        $this->addSql('ALTER TABLE credit_dossier ADD CONSTRAINT FK_7CC87184433ED66 FOREIGN KEY (parcelle_id) REFERENCES parcelle (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS irrigation_request');
        $this->addSql('DROP TABLE IF EXISTS culture');
        $this->addSql('DROP TABLE IF EXISTS parcelle');
    }
}

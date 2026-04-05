<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create parcelle, culture, and credit_dossier tables for Parcelle_Cultures module';
    }

    public function up(Schema $schema): void
    {
        // CREATE TABLE parcelle
        $this->addSql('CREATE TABLE parcelle (
            id INT AUTO_INCREMENT NOT NULL,
            agriculteur_id INT NOT NULL,
            surface DOUBLE PRECISION NOT NULL,
            localisation VARCHAR(255) NOT NULL,
            type_sol VARCHAR(100) NOT NULL,
            systeme_irrigation VARCHAR(100) NOT NULL,
            statut VARCHAR(50) NOT NULL DEFAULT "Active",
            latitude DOUBLE PRECISION DEFAULT NULL,
            longitude DOUBLE PRECISION DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            KEY IDX_parcelle_agriculteur (agriculteur_id),
            CONSTRAINT FK_parcelle_user FOREIGN KEY (agriculteur_id) REFERENCES user(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // CREATE TABLE culture
        $this->addSql('CREATE TABLE culture (
            id INT AUTO_INCREMENT NOT NULL,
            parcelle_id INT NOT NULL,
            nom_culture VARCHAR(255) NOT NULL,
            type_culture VARCHAR(100) NOT NULL,
            saison VARCHAR(50) NOT NULL,
            date_plantation DATE NOT NULL,
            date_recolte_prevue DATE NOT NULL,
            etat_culture VARCHAR(50) NOT NULL DEFAULT "Semée",
            surface_utilisee DOUBLE PRECISION NOT NULL,
            rendement_estime DOUBLE PRECISION NOT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            KEY IDX_culture_parcelle (parcelle_id),
            CONSTRAINT FK_culture_parcelle FOREIGN KEY (parcelle_id) REFERENCES parcelle(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // CREATE TABLE credit_dossier
        $this->addSql('CREATE TABLE credit_dossier (
            id INT AUTO_INCREMENT NOT NULL,
            parcelle_id INT NOT NULL,
            user_id INT NOT NULL,
            duree_annees INT NOT NULL,
            langue VARCHAR(10) NOT NULL DEFAULT "fr",
            score_risque DOUBLE PRECISION NOT NULL,
            niveau_risque VARCHAR(50) NOT NULL,
            montant_pret_max DOUBLE PRECISION NOT NULL,
            capacite_remboursement DOUBLE PRECISION NOT NULL,
            score_rentabilite DOUBLE PRECISION DEFAULT NULL,
            score_stabilite_climat DOUBLE PRECISION DEFAULT NULL,
            score_diversification DOUBLE PRECISION DEFAULT NULL,
            score_historique DOUBLE PRECISION DEFAULT NULL,
            date_creation DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            date_export DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            KEY IDX_credit_parcelle (parcelle_id),
            KEY IDX_credit_user (user_id),
            CONSTRAINT FK_credit_parcelle FOREIGN KEY (parcelle_id) REFERENCES parcelle(id) ON DELETE CASCADE,
            CONSTRAINT FK_credit_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS credit_dossier');
        $this->addSql('DROP TABLE IF EXISTS culture');
        $this->addSql('DROP TABLE IF EXISTS parcelle');
    }
}

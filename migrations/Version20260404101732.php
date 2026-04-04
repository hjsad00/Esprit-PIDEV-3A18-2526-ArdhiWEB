<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404101732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE badge CHANGE condition_type condition_type ENUM(\'DIAGNOSTIC\',\'POINTS\',\'HEALTHY_PLANTS\',\'SOLUTION\') DEFAULT \'DIAGNOSTIC\'');
        $this->addSql('ALTER TABLE community_likes CHANGE vote_type vote_type ENUM(\'LIKE\',\'DISLIKE\') NOT NULL DEFAULT \'LIKE\'');
        $this->addSql('ALTER TABLE diagnostic CHANGE confiance confiance DOUBLE PRECISION DEFAULT 0');
        $this->addSql('ALTER TABLE farm_health_scan CHANGE status status ENUM(\'PENDING\',\'PROCESSING\',\'COMPLETED\',\'FAILED\') DEFAULT \'PENDING\'');
        $this->addSql('ALTER TABLE prevention_plan CHANGE impact_level impact_level ENUM(\'HIGH\',\'MEDIUM\',\'LOW\'), CHANGE status status ENUM(\'ACTIVE\',\'COMPLETED\',\'ABANDONED\') DEFAULT \'ACTIVE\'');
        $this->addSql('ALTER TABLE prevention_task CHANGE status status ENUM(\'PENDING\',\'COMPLETED\',\'MISSED\') DEFAULT \'PENDING\'');
        $this->addSql('ALTER TABLE review CHANGE review_type review_type ENUM(\'DIAGNOSIS\',\'PROGRESS\',\'PREVENTION\') NOT NULL, CHANGE status status ENUM(\'PENDING\',\'IN_PROGRESS\',\'COMPLETED\') DEFAULT \'PENDING\', CHANGE expert_verdict expert_verdict ENUM(\'CONTINUE\',\'HEALED\',\'WORSENED\'), CHANGE farmer_response farmer_response ENUM(\'ACCEPTED\',\'REJECTED\',\'ACKNOWLEDGED\')');
        $this->addSql('ALTER TABLE traitement CHANGE type_traitement type_traitement ENUM(\'FONGICIDE\',\'HERBICIDE\',\'INSECTICIDE\',\'BACTERICIDE\',\'NEMATICIDE\',\'VIRUCIDE\',\'NUTRIMENT\',\'REGULATEUR_CROISSANCE\',\'AUTRE\') DEFAULT \'AUTRE\'');
        $this->addSql('ALTER TABLE traitement ADD CONSTRAINT FK_2A356D27224CCA91 FOREIGN KEY (diagnostic_id) REFERENCES diagnostic (id)');
        $this->addSql('CREATE INDEX IDX_2A356D27224CCA91 ON traitement (diagnostic_id)');
        $this->addSql('ALTER TABLE treatment_plan DROP FOREIGN KEY `treatment_plan_ibfk_1`');
        $this->addSql('ALTER TABLE treatment_plan CHANGE start_date start_date DATETIME DEFAULT NULL, CHANGE status status ENUM(\'ACTIVE\',\'COMPLETED\',\'ABANDONED\') DEFAULT \'ACTIVE\'');
        $this->addSql('ALTER TABLE treatment_plan ADD CONSTRAINT FK_1E99976C224CCA91 FOREIGN KEY (diagnostic_id) REFERENCES diagnostic (id)');
        $this->addSql('ALTER TABLE treatment_plan RENAME INDEX diagnostic_id TO IDX_1E99976C224CCA91');
        $this->addSql('ALTER TABLE treatment_task DROP FOREIGN KEY `treatment_task_ibfk_1`');
        $this->addSql('ALTER TABLE treatment_task CHANGE status status ENUM(\'PENDING\',\'COMPLETED\',\'MISSED\') DEFAULT \'PENDING\', CHANGE tech_x tech_x DOUBLE PRECISION DEFAULT NULL, CHANGE tech_y tech_y DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE treatment_task ADD CONSTRAINT FK_91BD1734905FC057 FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plan (id)');
        $this->addSql('ALTER TABLE treatment_task RENAME INDEX treatment_plan_id TO IDX_91BD1734905FC057');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ADMIN\',\'AGRICULTEUR\',\'CLIENT\',\'AGRONOME\') NOT NULL DEFAULT \'AGRICULTEUR\', CHANGE points points INT DEFAULT 0 NOT NULL, CHANGE level level INT DEFAULT 1 NOT NULL, CHANGE two_factor_enabled two_factor_enabled TINYINT DEFAULT 0 NOT NULL, CHANGE fingerprint_signature fingerprint_signature LONGTEXT DEFAULT NULL, CHANGE points_fidelite points_fidelite DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE reset_password_code reset_password_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_badge CHANGE acquired_at acquired_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_badge RENAME INDEX badge_id TO IDX_1C32B345F7A2C2FC');
        $this->addSql('ALTER TABLE vulnerability DROP FOREIGN KEY `vulnerability_ibfk_1`');
        $this->addSql('ALTER TABLE vulnerability CHANGE type type ENUM(\'PEST_OUTBREAK_RISK\',\'DISEASE_RISK\',\'NUTRIENT_DEFICIENCY\',\'LOW_POLLINATION\',\'SOIL_DEGRADATION\') NOT NULL, CHANGE severity severity ENUM(\'CRITICAL\',\'MEDIUM\',\'LOW\') NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT NULL, CHANGE estimated_cost_if_occurs estimated_cost_if_occurs DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT FK_6C4E40474BD2A4C0 FOREIGN KEY (report_id) REFERENCES farm_health_report (id)');
        $this->addSql('ALTER TABLE vulnerability RENAME INDEX report_id TO IDX_6C4E40474BD2A4C0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE badge CHANGE condition_type condition_type ENUM(\'DIAGNOSTIC\', \'POINTS\', \'HEALTHY_PLANTS\', \'SOLUTION\') DEFAULT \'DIAGNOSTIC\'');
        $this->addSql('ALTER TABLE community_likes CHANGE vote_type vote_type ENUM(\'LIKE\', \'DISLIKE\') DEFAULT \'LIKE\' NOT NULL');
        $this->addSql('ALTER TABLE diagnostic CHANGE confiance confiance DOUBLE PRECISION DEFAULT \'0\'');
        $this->addSql('ALTER TABLE farm_health_scan CHANGE status status ENUM(\'PENDING\', \'PROCESSING\', \'COMPLETED\', \'FAILED\') DEFAULT \'PENDING\'');
        $this->addSql('ALTER TABLE prevention_plan CHANGE impact_level impact_level ENUM(\'HIGH\', \'MEDIUM\', \'LOW\') DEFAULT NULL, CHANGE status status ENUM(\'ACTIVE\', \'COMPLETED\', \'ABANDONED\') DEFAULT \'ACTIVE\'');
        $this->addSql('ALTER TABLE prevention_task CHANGE status status ENUM(\'PENDING\', \'COMPLETED\', \'MISSED\') DEFAULT \'PENDING\'');
        $this->addSql('ALTER TABLE review CHANGE review_type review_type ENUM(\'DIAGNOSIS\', \'PROGRESS\', \'PREVENTION\') NOT NULL, CHANGE status status ENUM(\'PENDING\', \'IN_PROGRESS\', \'COMPLETED\') DEFAULT \'PENDING\', CHANGE expert_verdict expert_verdict ENUM(\'CONTINUE\', \'HEALED\', \'WORSENED\') DEFAULT NULL, CHANGE farmer_response farmer_response ENUM(\'ACCEPTED\', \'REJECTED\', \'ACKNOWLEDGED\') DEFAULT NULL');
        $this->addSql('ALTER TABLE traitement DROP FOREIGN KEY FK_2A356D27224CCA91');
        $this->addSql('DROP INDEX IDX_2A356D27224CCA91 ON traitement');
        $this->addSql('ALTER TABLE traitement CHANGE type_traitement type_traitement ENUM(\'FONGICIDE\', \'HERBICIDE\', \'INSECTICIDE\', \'BACTERICIDE\', \'NEMATICIDE\', \'VIRUCIDE\', \'NUTRIMENT\', \'REGULATEUR_CROISSANCE\', \'AUTRE\') DEFAULT \'AUTRE\'');
        $this->addSql('ALTER TABLE treatment_plan DROP FOREIGN KEY FK_1E99976C224CCA91');
        $this->addSql('ALTER TABLE treatment_plan CHANGE start_date start_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE status status ENUM(\'ACTIVE\', \'COMPLETED\', \'ABANDONED\') DEFAULT \'ACTIVE\'');
        $this->addSql('ALTER TABLE treatment_plan ADD CONSTRAINT `treatment_plan_ibfk_1` FOREIGN KEY (diagnostic_id) REFERENCES diagnostic (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE treatment_plan RENAME INDEX idx_1e99976c224cca91 TO diagnostic_id');
        $this->addSql('ALTER TABLE treatment_task DROP FOREIGN KEY FK_91BD1734905FC057');
        $this->addSql('ALTER TABLE treatment_task CHANGE status status ENUM(\'PENDING\', \'COMPLETED\', \'MISSED\') DEFAULT \'PENDING\', CHANGE tech_x tech_x DOUBLE PRECISION DEFAULT \'0\', CHANGE tech_y tech_y DOUBLE PRECISION DEFAULT \'0\'');
        $this->addSql('ALTER TABLE treatment_task ADD CONSTRAINT `treatment_task_ibfk_1` FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plan (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE treatment_task RENAME INDEX idx_91bd1734905fc057 TO treatment_plan_id');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ADMIN\', \'AGRICULTEUR\', \'CLIENT\', \'AGRONOME\') DEFAULT \'AGRICULTEUR\' NOT NULL, CHANGE points points INT DEFAULT 0, CHANGE level level INT DEFAULT 1, CHANGE two_factor_enabled two_factor_enabled TINYINT DEFAULT 0, CHANGE fingerprint_signature fingerprint_signature MEDIUMTEXT DEFAULT NULL, CHANGE points_fidelite points_fidelite DOUBLE PRECISION DEFAULT \'0\', CHANGE reset_password_code reset_password_code VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_badge CHANGE acquired_at acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE user_badge RENAME INDEX idx_1c32b345f7a2c2fc TO badge_id');
        $this->addSql('ALTER TABLE vulnerability DROP FOREIGN KEY FK_6C4E40474BD2A4C0');
        $this->addSql('ALTER TABLE vulnerability CHANGE type type ENUM(\'PEST_OUTBREAK_RISK\', \'DISEASE_RISK\', \'NUTRIENT_DEFICIENCY\', \'LOW_POLLINATION\', \'SOIL_DEGRADATION\') NOT NULL, CHANGE severity severity ENUM(\'CRITICAL\', \'MEDIUM\', \'LOW\') NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE risk_score risk_score FLOAT DEFAULT NULL, CHANGE estimated_cost_if_occurs estimated_cost_if_occurs FLOAT DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT `vulnerability_ibfk_1` FOREIGN KEY (report_id) REFERENCES farm_health_report (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vulnerability RENAME INDEX idx_6c4e40474bd2a4c0 TO report_id');
    }
}

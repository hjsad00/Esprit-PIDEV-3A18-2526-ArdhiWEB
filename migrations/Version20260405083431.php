<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405083431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Google OAuth tokens to user table';
    }

    public function up(Schema $schema): void
    {
        // Add only the two required columns to user table safely
        $this->addSql('ALTER TABLE user ADD google_access_token LONGTEXT DEFAULT NULL, ADD google_refresh_token LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP google_access_token, DROP google_refresh_token');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reminder sent flags for participation email notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation ADD rappel_j3_envoye TINYINT(1) NOT NULL DEFAULT 0, ADD rappel_j1_envoye TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation DROP rappel_j3_envoye, DROP rappel_j1_envoye');
    }
}


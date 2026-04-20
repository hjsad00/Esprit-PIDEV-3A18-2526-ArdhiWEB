<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420AddPolygonGeojson extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add polygon_geojson field to parcelle table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcelle ADD polygon_geojson JSON DEFAULT NULL COMMENT "(DC2Type:json)"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcelle DROP COLUMN polygon_geojson');
    }
}

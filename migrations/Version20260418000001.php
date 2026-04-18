<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add review_comments table for avis/review replies';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review_comments (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, participation_id INT NOT NULL, user_id INT NOT NULL, parent_comment_id INT DEFAULT NULL, INDEX IDX_7CDC1F216ACE3B73 (participation_id), INDEX IDX_7CDC1F21A76ED395 (user_id), INDEX IDX_7CDC1F21BF2AF943 (parent_comment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE review_comments ADD CONSTRAINT FK_7CDC1F216ACE3B73 FOREIGN KEY (participation_id) REFERENCES participation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review_comments ADD CONSTRAINT FK_7CDC1F21A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review_comments ADD CONSTRAINT FK_7CDC1F21BF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES review_comments (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review_comments DROP FOREIGN KEY FK_7CDC1F216ACE3B73');
        $this->addSql('ALTER TABLE review_comments DROP FOREIGN KEY FK_7CDC1F21A76ED395');
        $this->addSql('ALTER TABLE review_comments DROP FOREIGN KEY FK_7CDC1F21BF2AF943');
        $this->addSql('DROP TABLE review_comments');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020093328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE personnel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, matricule VARCHAR(50) NOT NULL, nom VARCHAR(150) NOT NULL, type VARCHAR(50) NOT NULL, service VARCHAR(100) DEFAULT NULL, email VARCHAR(150) DEFAULT NULL, telephone VARCHAR(50) DEFAULT NULL, statut VARCHAR(30) NOT NULL, date_ajout DATETIME NOT NULL, notes CLOB DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A6BCF3DE12B2DC9C ON personnel (matricule)');
        $this->addSql('CREATE INDEX idx_personnel_matricule ON personnel (matricule)');
        $this->addSql('CREATE INDEX idx_personnel_type ON personnel (type)');
        $this->addSql('CREATE INDEX idx_personnel_service ON personnel (service)');

        $this->addSql('ALTER TABLE attribution ADD COLUMN personnel_id INTEGER DEFAULT NULL REFERENCES personnel (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C751ED491C109075 ON attribution (personnel_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IF EXISTS IDX_C751ED491C109075');
        $this->addSql('DROP TABLE personnel');
    }
}

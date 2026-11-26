<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022162652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE checklist_etape (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, cle VARCHAR(80) NOT NULL, libelle VARCHAR(200) NOT NULL, description CLOB DEFAULT NULL, groupe VARCHAR(30) NOT NULL, ordre INTEGER NOT NULL, statut VARCHAR(20) NOT NULL, observations CLOB DEFAULT NULL, date_validation DATETIME DEFAULT NULL, icone VARCHAR(60) DEFAULT NULL, preparation_id INTEGER NOT NULL, CONSTRAINT FK_16B7F2033DD9B8BA FOREIGN KEY (preparation_id) REFERENCES checklist_preparation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_16B7F2033DD9B8BA ON checklist_etape (preparation_id)');
        $this->addSql('CREATE TABLE checklist_preparation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME DEFAULT NULL, observations CLOB DEFAULT NULL, equipement_id INTEGER NOT NULL, technicien_id INTEGER DEFAULT NULL, CONSTRAINT FK_EF3C1A64806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EF3C1A6413457256 FOREIGN KEY (technicien_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EF3C1A64806F0F5C ON checklist_preparation (equipement_id)');
        $this->addSql('CREATE INDEX IDX_EF3C1A6413457256 ON checklist_preparation (technicien_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE checklist_etape');
        $this->addSql('DROP TABLE checklist_preparation');
    }
}

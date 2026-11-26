<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022115541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE campagne_inventaire (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, annee INTEGER NOT NULL, date_debut DATETIME NOT NULL, date_cloture DATETIME DEFAULT NULL, statut VARCHAR(30) NOT NULL, cree_par VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL)');
        $this->addSql('CREATE TABLE ligne_inventaire (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, statut_constate VARCHAR(50) NOT NULL, lieu_attendu VARCHAR(100) DEFAULT NULL, date_pointage DATETIME DEFAULT NULL, pointe_par VARCHAR(100) DEFAULT NULL, observations CLOB DEFAULT NULL, campagne_id INTEGER NOT NULL, equipement_id INTEGER NOT NULL, lieu_constate_id INTEGER DEFAULT NULL, CONSTRAINT FK_D025CEFD16227374 FOREIGN KEY (campagne_id) REFERENCES campagne_inventaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D025CEFD806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D025CEFDBA5FBA62 FOREIGN KEY (lieu_constate_id) REFERENCES lieu (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D025CEFD16227374 ON ligne_inventaire (campagne_id)');
        $this->addSql('CREATE INDEX IDX_D025CEFD806F0F5C ON ligne_inventaire (equipement_id)');
        $this->addSql('CREATE INDEX IDX_D025CEFDBA5FBA62 ON ligne_inventaire (lieu_constate_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE campagne_inventaire');
        $this->addSql('DROP TABLE ligne_inventaire');
    }
}

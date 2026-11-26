<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005093210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribution (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, beneficiaire_nom VARCHAR(150) NOT NULL, beneficiaire_service VARCHAR(100) DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin_prevue DATETIME DEFAULT NULL, date_restitution DATETIME DEFAULT NULL, statut VARCHAR(50) NOT NULL, motif CLOB DEFAULT NULL, notes CLOB DEFAULT NULL, equipement_id INTEGER NOT NULL, CONSTRAINT FK_C751ED49806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C751ED49806F0F5C ON attribution (equipement_id)');
        $this->addSql('CREATE TABLE equipement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, numero_serie VARCHAR(100) NOT NULL, tag_inventaire VARCHAR(50) DEFAULT NULL, marque VARCHAR(100) NOT NULL, modele VARCHAR(100) NOT NULL, statut VARCHAR(50) NOT NULL, date_acquisition DATE DEFAULT NULL, fin_garantie DATE DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, notes CLOB DEFAULT NULL, date_ajout DATETIME NOT NULL, categorie_id INTEGER DEFAULT NULL, lieu_id INTEGER DEFAULT NULL, CONSTRAINT FK_B8B4C6F3BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_equipement (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B8B4C6F36AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8B4C6F3565B809 ON equipement (numero_serie)');
        $this->addSql('CREATE INDEX IDX_B8B4C6F3BCF5E72D ON equipement (categorie_id)');
        $this->addSql('CREATE INDEX IDX_B8B4C6F36AB213CC ON equipement (lieu_id)');
        $this->addSql('CREATE TABLE ticket_maintenance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description CLOB NOT NULL, priorite VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, technicien VARCHAR(100) DEFAULT NULL, cout DOUBLE PRECISION DEFAULT NULL, date_creation DATETIME NOT NULL, date_resolution DATETIME DEFAULT NULL, solution CLOB DEFAULT NULL, equipement_id INTEGER NOT NULL, CONSTRAINT FK_4314FE96806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4314FE96806F0F5C ON ticket_maintenance (equipement_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE attribution');
        $this->addSql('DROP TABLE equipement');
        $this->addSql('DROP TABLE ticket_maintenance');
    }
}

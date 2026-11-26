<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005104030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE composant (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, type VARCHAR(50) NOT NULL, numero_serie VARCHAR(100) DEFAULT NULL, quantite INTEGER DEFAULT 1 NOT NULL, statut VARCHAR(50) NOT NULL, date_acquisition DATE DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, notes CLOB DEFAULT NULL, date_ajout DATETIME NOT NULL, equipement_id INTEGER DEFAULT NULL, lieu_id INTEGER DEFAULT NULL, CONSTRAINT FK_EC8486C9806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EC8486C96AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EC8486C9806F0F5C ON composant (equipement_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C96AB213CC ON composant (lieu_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__attribution AS SELECT id, beneficiaire_nom, beneficiaire_service, date_debut, date_fin_prevue, date_restitution, statut, motif, notes, equipement_id FROM attribution');
        $this->addSql('DROP TABLE attribution');
        $this->addSql('CREATE TABLE attribution (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, beneficiaire_nom VARCHAR(150) NOT NULL, beneficiaire_service VARCHAR(100) DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin_prevue DATETIME DEFAULT NULL, date_restitution DATETIME DEFAULT NULL, statut VARCHAR(50) NOT NULL, motif CLOB DEFAULT NULL, notes CLOB DEFAULT NULL, equipement_id INTEGER DEFAULT NULL, CONSTRAINT FK_C751ED49806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO attribution (id, beneficiaire_nom, beneficiaire_service, date_debut, date_fin_prevue, date_restitution, statut, motif, notes, equipement_id) SELECT id, beneficiaire_nom, beneficiaire_service, date_debut, date_fin_prevue, date_restitution, statut, motif, notes, equipement_id FROM __temp__attribution');
        $this->addSql('DROP TABLE __temp__attribution');
        $this->addSql('CREATE INDEX IDX_C751ED49806F0F5C ON attribution (equipement_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_maintenance AS SELECT id, titre, description, priorite, statut, technicien, cout, date_creation, date_resolution, solution, equipement_id FROM ticket_maintenance');
        $this->addSql('DROP TABLE ticket_maintenance');
        $this->addSql('CREATE TABLE ticket_maintenance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description CLOB NOT NULL, priorite VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, technicien VARCHAR(100) DEFAULT NULL, cout DOUBLE PRECISION DEFAULT NULL, date_creation DATETIME NOT NULL, date_resolution DATETIME DEFAULT NULL, solution CLOB DEFAULT NULL, equipement_id INTEGER DEFAULT NULL, CONSTRAINT FK_4314FE96806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_maintenance (id, titre, description, priorite, statut, technicien, cout, date_creation, date_resolution, solution, equipement_id) SELECT id, titre, description, priorite, statut, technicien, cout, date_creation, date_resolution, solution, equipement_id FROM __temp__ticket_maintenance');
        $this->addSql('DROP TABLE __temp__ticket_maintenance');
        $this->addSql('CREATE INDEX IDX_4314FE96806F0F5C ON ticket_maintenance (equipement_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE composant');
        $this->addSql('CREATE TEMPORARY TABLE __temp__attribution AS SELECT id, beneficiaire_nom, beneficiaire_service, date_debut, date_fin_prevue, date_restitution, statut, motif, notes, equipement_id FROM attribution');
        $this->addSql('DROP TABLE attribution');
        $this->addSql('CREATE TABLE attribution (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, beneficiaire_nom VARCHAR(150) NOT NULL, beneficiaire_service VARCHAR(100) DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin_prevue DATETIME DEFAULT NULL, date_restitution DATETIME DEFAULT NULL, statut VARCHAR(50) NOT NULL, motif CLOB DEFAULT NULL, notes CLOB DEFAULT NULL, equipement_id INTEGER NOT NULL, CONSTRAINT FK_C751ED49806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO attribution (id, beneficiaire_nom, beneficiaire_service, date_debut, date_fin_prevue, date_restitution, statut, motif, notes, equipement_id) SELECT id, beneficiaire_nom, beneficiaire_service, date_debut, date_fin_prevue, date_restitution, statut, motif, notes, equipement_id FROM __temp__attribution');
        $this->addSql('DROP TABLE __temp__attribution');
        $this->addSql('CREATE INDEX IDX_C751ED49806F0F5C ON attribution (equipement_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_maintenance AS SELECT id, titre, description, priorite, statut, technicien, cout, date_creation, date_resolution, solution, equipement_id FROM ticket_maintenance');
        $this->addSql('DROP TABLE ticket_maintenance');
        $this->addSql('CREATE TABLE ticket_maintenance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description CLOB NOT NULL, priorite VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, technicien VARCHAR(100) DEFAULT NULL, cout DOUBLE PRECISION DEFAULT NULL, date_creation DATETIME NOT NULL, date_resolution DATETIME DEFAULT NULL, solution CLOB DEFAULT NULL, equipement_id INTEGER NOT NULL, CONSTRAINT FK_4314FE96806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_maintenance (id, titre, description, priorite, statut, technicien, cout, date_creation, date_resolution, solution, equipement_id) SELECT id, titre, description, priorite, statut, technicien, cout, date_creation, date_resolution, solution, equipement_id FROM __temp__ticket_maintenance');
        $this->addSql('DROP TABLE __temp__ticket_maintenance');
        $this->addSql('CREATE INDEX IDX_4314FE96806F0F5C ON ticket_maintenance (equipement_id)');
    }
}

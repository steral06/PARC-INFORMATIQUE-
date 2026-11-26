<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005171707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD COLUMN adresse_ip VARCHAR(45) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__equipement AS SELECT id, nom, numero_serie, tag_inventaire, marque, modele, statut, date_acquisition, fin_garantie, prix_achat, notes, date_ajout, categorie_id, lieu_id FROM equipement');
        $this->addSql('DROP TABLE equipement');
        $this->addSql('CREATE TABLE equipement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, numero_serie VARCHAR(100) DEFAULT NULL, tag_inventaire VARCHAR(50) DEFAULT NULL, marque VARCHAR(100) DEFAULT NULL, modele VARCHAR(100) NOT NULL, statut VARCHAR(50) NOT NULL, date_acquisition DATE DEFAULT NULL, fin_garantie DATE DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, notes CLOB DEFAULT NULL, date_ajout DATETIME NOT NULL, categorie_id INTEGER DEFAULT NULL, lieu_id INTEGER DEFAULT NULL, CONSTRAINT FK_B8B4C6F3BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_equipement (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B8B4C6F36AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO equipement (id, nom, numero_serie, tag_inventaire, marque, modele, statut, date_acquisition, fin_garantie, prix_achat, notes, date_ajout, categorie_id, lieu_id) SELECT id, nom, numero_serie, tag_inventaire, marque, modele, statut, date_acquisition, fin_garantie, prix_achat, notes, date_ajout, categorie_id, lieu_id FROM __temp__equipement');
        $this->addSql('DROP TABLE __temp__equipement');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8B4C6F3565B809 ON equipement (numero_serie)');
        $this->addSql('CREATE INDEX IDX_B8B4C6F3BCF5E72D ON equipement (categorie_id)');
        $this->addSql('CREATE INDEX IDX_B8B4C6F36AB213CC ON equipement (lieu_id)');
    }
}

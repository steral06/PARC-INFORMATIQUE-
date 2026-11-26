<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002165249 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__poste_autorise AS SELECT id, nom, adresse_ip, description FROM poste_autorise');
        $this->addSql('DROP TABLE poste_autorise');
        $this->addSql('CREATE TABLE poste_autorise (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, adresse_ip VARCHAR(45) NOT NULL, description CLOB DEFAULT NULL, is_autorise BOOLEAN DEFAULT 1 NOT NULL, date_premiere_connexion DATETIME DEFAULT NULL, date_derniere_connexion DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO poste_autorise (id, nom, adresse_ip, description) SELECT id, nom, adresse_ip, description FROM __temp__poste_autorise');
        $this->addSql('DROP TABLE __temp__poste_autorise');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_36721B7573D0821 ON poste_autorise (adresse_ip)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__poste_autorise AS SELECT id, nom, adresse_ip, description FROM poste_autorise');
        $this->addSql('DROP TABLE poste_autorise');
        $this->addSql('CREATE TABLE poste_autorise (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, adresse_ip VARCHAR(45) NOT NULL, description CLOB DEFAULT NULL)');
        $this->addSql('INSERT INTO poste_autorise (id, nom, adresse_ip, description) SELECT id, nom, adresse_ip, description FROM __temp__poste_autorise');
        $this->addSql('DROP TABLE __temp__poste_autorise');
    }
}

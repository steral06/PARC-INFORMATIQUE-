<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Composant — ajout des champs structurés : marque, modele, capacite, frequence.
 * Permet de décrire chaque composant par catégorie avec des attributs contextuels
 * plutôt qu'un unique champ texte libre.
 */
final class Version20261023153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Composant : ajout marque, modele, capacite, frequence (champs structurés par catégorie)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE composant ADD COLUMN marque VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE composant ADD COLUMN modele VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE composant ADD COLUMN capacite VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE composant ADD COLUMN frequence VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite ne supporte pas DROP COLUMN avant la version 3.35.
        // On recrée la table sans ces colonnes.
        $this->addSql('CREATE TABLE composant_backup AS SELECT id, nom, type, numero_serie, quantite, statut, equipement_id, lieu_id, date_acquisition, prix_achat, notes, date_ajout FROM composant');
        $this->addSql('DROP TABLE composant');
        $this->addSql('ALTER TABLE composant_backup RENAME TO composant');
    }
}


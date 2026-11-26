<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour la table sauvegarde_poste (Points de restauration et Sauvegardes profils incrémentielles)
 */
final class Version20261027154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table sauvegarde_poste pour le suivi des sauvegardes et points de restauration distants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sauvegarde_poste (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            equipement_id INTEGER DEFAULT NULL,
            nom_machine VARCHAR(100) NOT NULL,
            adresse_ip VARCHAR(50) DEFAULT NULL,
            type_operation VARCHAR(50) NOT NULL,
            statut VARCHAR(30) NOT NULL,
            lance_par VARCHAR(100) DEFAULT NULL,
            details CLOB DEFAULT NULL,
            taille_estimee VARCHAR(50) DEFAULT NULL,
            date_execution DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , CONSTRAINT FK_SAUVEGARDE_EQUIPEMENT FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_SAUVEGARDE_EQUIPEMENT ON sauvegarde_poste (equipement_id)');
        $this->addSql('CREATE INDEX IDX_SAUVEGARDE_TYPE ON sauvegarde_poste (type_operation)');
        $this->addSql('CREATE INDEX IDX_SAUVEGARDE_DATE ON sauvegarde_poste (date_execution)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sauvegarde_poste');
    }
}


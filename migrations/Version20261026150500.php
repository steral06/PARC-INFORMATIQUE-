<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour TicketMaintenance : distinction maintenance DSI (interne) vs Prestataire (externe).
 */
final class Version20261026150500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du type_maintenance (DSI vs Prestataire), prestataire_nom, numero_bon et date_retour_prevue sur ticket_maintenance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ticket_maintenance ADD COLUMN type_maintenance VARCHAR(30) NOT NULL DEFAULT 'DSI'");
        $this->addSql("ALTER TABLE ticket_maintenance ADD COLUMN prestataire_nom VARCHAR(100) DEFAULT NULL");
        $this->addSql("ALTER TABLE ticket_maintenance ADD COLUMN numero_bon VARCHAR(50) DEFAULT NULL");
        $this->addSql("ALTER TABLE ticket_maintenance ADD COLUMN date_retour_prevue DATE DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        // En SQLite, pas de DROP COLUMN sans recréer la table
        $this->addSql('CREATE TABLE ticket_maintenance_backup AS SELECT id, equipement_id, titre, description, priorite, statut, technicien, cout, date_creation, date_resolution, solution FROM ticket_maintenance');
        $this->addSql('DROP TABLE ticket_maintenance');
        $this->addSql('ALTER TABLE ticket_maintenance_backup RENAME TO ticket_maintenance');
    }
}


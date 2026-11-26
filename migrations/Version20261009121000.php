<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit_log table for integrity/audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(100) DEFAULT NULL,
            entity_id INTEGER DEFAULT NULL,
            user_identifier VARCHAR(180) DEFAULT NULL,
            details JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE INDEX idx_audit_action ON audit_log (action)');
        $this->addSql('CREATE INDEX idx_audit_entity ON audit_log (entity_type)');
        $this->addSql('CREATE INDEX idx_audit_user ON audit_log (user_identifier)');
        $this->addSql('CREATE INDEX idx_audit_created ON audit_log (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
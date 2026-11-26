<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update audit_log table with new fields for audit journal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
        
        $this->addSql('CREATE TABLE audit_log (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            action VARCHAR(50) NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT \'INFO\',
            status VARCHAR(20) NOT NULL DEFAULT \'SUCCES\',
            entity_type VARCHAR(100) DEFAULT NULL,
            entity_id VARCHAR(100) DEFAULT NULL,
            username VARCHAR(180) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            details JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            previous_hash VARCHAR(64) DEFAULT NULL,
            hash VARCHAR(64) DEFAULT NULL
        )');
        
        $this->addSql('CREATE INDEX idx_audit_action ON audit_log (action)');
        $this->addSql('CREATE INDEX idx_audit_level ON audit_log (level)');
        $this->addSql('CREATE INDEX idx_audit_status ON audit_log (status)');
        $this->addSql('CREATE INDEX idx_audit_entity ON audit_log (entity_type)');
        $this->addSql('CREATE INDEX idx_audit_user ON audit_log (username)');
        $this->addSql('CREATE INDEX idx_audit_created ON audit_log (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
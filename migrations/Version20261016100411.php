<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016100411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create webauthn_credential table for biometric authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE webauthn_credential (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, credential_id VARCHAR(512) NOT NULL, public_key CLOB NOT NULL, algorithm INTEGER NOT NULL, sign_count INTEGER NOT NULL, device_name VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_850123F9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_850123F92558A7A5 ON webauthn_credential (credential_id)');
        $this->addSql('CREATE INDEX IDX_850123F9A76ED395 ON webauthn_credential (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE webauthn_credential');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925180403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lease-based claiming fields to outbox events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE outbox_events
         ADD claimed_at DATETIME DEFAULT NULL,
         ADD claim_token CHAR(36) DEFAULT NULL'
        );

        $this->addSql(
            'DROP INDEX idx_outbox_unprocessed ON outbox_events'
        );

        $this->addSql(
            'CREATE INDEX idx_outbox_claimable
         ON outbox_events (processed_at, claimed_at, id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX idx_outbox_claimable ON outbox_events'
        );

        $this->addSql(
            'CREATE INDEX idx_outbox_unprocessed
         ON outbox_events (processed_at, id)'
        );

        $this->addSql(
            'ALTER TABLE outbox_events
         DROP COLUMN claim_token,
         DROP COLUMN claimed_at'
        );
    }
}

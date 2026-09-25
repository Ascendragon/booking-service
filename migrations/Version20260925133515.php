<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925133515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event id and rename outbox created_at to occurred_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE outbox_events ADD event_id CHAR(36) DEFAULT NULL");

        $this->addSql("UPDATE outbox_events SET event_id = UUID() WHERE event_id IS NULL");

        $this->addSql("ALTER TABLE outbox_events MODIFY event_id CHAR(36) NOT NULL");

        $this->addSql("CREATE UNIQUE INDEX uniq_outbox_event_id ON outbox_events (event_id)");

        $this->addSql(
            'ALTER TABLE outbox_events RENAME COLUMN created_at TO occurred_at'
        );

    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE outbox_events RENAME COLUMN occurred_at TO created_at'
        );

        $this->addSql(
            'DROP INDEX uniq_outbox_event_id ON outbox_events'
        );

        $this->addSql(
            'ALTER TABLE outbox_events DROP COLUMN event_id'
        );

    }
}

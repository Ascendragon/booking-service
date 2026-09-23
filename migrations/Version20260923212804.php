<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260923212804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
        CREATE TABLE outbox_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            aggregate_type VARCHAR(100) NOT NULL,
            aggregate_id BIGINT NOT NULL,
            payload JSON NOT NULL,
            created_at DATETIME NOT NULL,
            processed_at DATETIME NULL
        )
    ');

        $this->addSql('
        CREATE INDEX idx_outbox_unprocessed
        ON outbox_events(processed_at, id)
    ');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE outbox_events');

    }
}

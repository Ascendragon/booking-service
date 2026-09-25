<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925132144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove redundant bookings slot index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX idx_bookings_slot ON bookings'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE INDEX idx_bookings_slot ON bookings (slot_id)'
        );
    }
}

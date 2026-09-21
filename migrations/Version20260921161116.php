<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921161116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
        CREATE TABLE employees (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            position VARCHAR(128) DEFAULT NULL,
            PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

        $this->addSql('
        CREATE TABLE slots (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,

            INDEX idx_slots_employee_starts_at (
                employee_id,
                starts_at
            ),

            PRIMARY KEY(id),

            CONSTRAINT fk_slots_employee
                FOREIGN KEY (employee_id)
                REFERENCES employees(id),

            CONSTRAINT chk_slots_time
                CHECK (ends_at > starts_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

        $this->addSql('
        CREATE TABLE bookings (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            slot_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_bookings_slot (slot_id),

            PRIMARY KEY(id),

            CONSTRAINT fk_bookings_slot
                FOREIGN KEY (slot_id)
                REFERENCES slots(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bookings');
        $this->addSql('DROP TABLE slots');
        $this->addSql('DROP TABLE employees');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Booking\Application;

use App\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Booking\Domain\Booking;
use App\Booking\Infrastructure\Persistence\DbalBookingRepository;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Database\DbalTransactionManager;
use App\Outbox\Domain\OutboxRepository;
use App\Outbox\Infrastructure\Persistence\DbalOutboxRepository;
use App\Shared\Domain\DomainEvent;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateBookingHandlerIntegrationTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);

        $this->connection->delete('outbox_events');
        $this->connection->delete('bookings');
        $this->connection->delete('slots');
        $this->connection->delete('employees');
    }

    public function testCreatesBookingAndOutboxEventAtomically(): void
    {
        $slotId = $this->createSlot();
        $clock = new SystemClock();

        $handler = new CreateBookingHandler(
            new DbalBookingRepository($this->connection),
            $clock,
            new DbalTransactionManager($this->connection),
            new DbalOutboxRepository($this->connection, $clock),
        );
        $command = new CreateBookingCommand($slotId, 100);
        $bookingId = $handler($command);

        $bookingCount = (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM bookings WHERE id = ?',
            [$bookingId]
        );

        $event = $this->connection->fetchAssociative(
            'SELECT * FROM outbox_events WHERE aggregate_id = ?',
            [$bookingId]
        );

        self::assertSame(1, $bookingCount);
        self::assertIsArray($event);
        self::assertSame('booking.created.v1', $event['event_type']);
        self::assertSame('Booking', $event['aggregate_type']);

        $payload = json_decode($event['payload'], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($bookingId, $payload['bookingId']);
        self::assertSame($slotId, $payload['slotId']);
        self::assertSame(100, $payload['customerId']);
    }

    private function createSlot(): int
    {
        $this->connection->insert('employees', [
            'name' => 'John Doe',
            'position' => 'Barber'
        ]);

        $employeeId = (int)$this->connection->lastInsertId();

        $this->connection->insert('slots', [
            'employee_id' => $employeeId,
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-01 11:00:00',
        ]);

        return (int) $this->connection->lastInsertId();


    }

    public function testRollsBackBookingWhenOutboxWriteFails(): void
    {
        $slotId = $this->createSlot();

        $clock = new SystemClock();

        $failingOutbox = new class implements OutboxRepository {
            public function save(
                DomainEvent $event,
            ): void {
                throw new \RuntimeException('Outbox unavailable');
            }
        };

        $handler = new CreateBookingHandler(
            new DbalBookingRepository($this->connection),
            $clock,
            new DbalTransactionManager($this->connection),
            $failingOutbox,
        );
        try {
            $handler(new CreateBookingCommand($slotId, 100));

            self::fail('Expected outbox failure');
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'Outbox unavailable',
                $exception->getMessage(),
            );
        }
        $bookingCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM bookings WHERE slot_id = ?',
            [$slotId],
        );

        self::assertSame(0, $bookingCount);
    }
}

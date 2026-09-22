<?php

declare(strict_types=1);

namespace App\Tests\Integration\Booking\Infrastructure\Persistence;

use AllowDynamicProperties;
use App\Booking\Domain\Booking;
use App\Booking\Infrastructure\Persistence\DbalBookingRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DbalBookingRepositoryTest extends KernelTestCase
{
    private int $slotId;
    private Connection $connection;
    private DbalBookingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);
        $this->repository = new DbalBookingRepository(
            $this->connection,
        );

        $this->connection->delete('bookings');
        $this->connection->delete('slots');
        $this->connection->delete('employees');

        $this->connection->insert('employees', [
            'name' => 'John Doe',
            'position' => 'Barber',
        ]);

        $employeeId = (int)$this->connection->lastInsertId();

        $this->connection->insert('slots', [
            'employee_id' => $employeeId,
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-01 10:30:00',
        ]);

        $this->slotId = (int)$this->connection->lastInsertId();
    }

    public function testSlotWithoutBookingIsNotBooked(): void
    {
        $result = $this->repository->existsForSlot($this->slotId);

        self::assertFalse($result);
    }

    public function testSavedBookingCanBeFoundBySlot(): void
    {
        $booking = Booking::create($this->slotId, 100, new DateTimeImmutable('2030-01-01 09:55:00'));

        $bookingId = $this->repository->save($booking);

        self::assertGreaterThan(0, $bookingId);
        self::assertTrue($this->repository->existsForSlot($this->slotId));

    }


}

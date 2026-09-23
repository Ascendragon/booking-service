<?php

namespace App\Tests\Integration\Booking;

use App\Booking\Domain\Booking;
use App\Booking\Domain\Exception\SlotAlreadyBooked;
use App\Booking\Infrastructure\Persistence\DbalBookingRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


final class RaceConditionTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);

        $this->connection->delete('bookings');
        $this->connection->delete('slots');
        $this->connection->delete('employees');

        $this->connection->insert('employees', [
            'name' => 'John Doe',
            'position' => 'Barber',
        ]);

        $employeeId = (int) $this->connection->lastInsertId();

        $this->connection->insert('slots', [
            'employee_id' => $employeeId,
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-01 10:30:00',
        ]);
    }
    public function testTwoConnectionsCannotCreateTwoBookings(): void
    {
        $connection2 = $this->createSecondConnection();

        $repositoryA = new DbalBookingRepository($this->connection);

        $repositoryB = new DbalBookingRepository($connection2);

        $slotId = (int) $this->connection->fetchOne(
            "SELECT id FROM slots LIMIT 1"
        );

        $bookingA = Booking::create($slotId, 100, new DateTimeImmutable());

        $bookingB = Booking::create(
            $slotId,
            200,
            new DateTimeImmutable()
        );

        self::assertFalse(
            $repositoryA->existsForSlot($slotId)
        );

        self::assertFalse(
            $repositoryB->existsForSlot($slotId)
        );

        $repositoryA->save($bookingA);


        $this->expectException(
            SlotAlreadyBooked::class
        );

        $repositoryB->save($bookingB);

    }

    private function createSecondConnection(): Connection
    {
        return DriverManager::getConnection(
            $this->connection->getParams()
        );
    }
}

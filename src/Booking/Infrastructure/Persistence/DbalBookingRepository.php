<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure\Persistence;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingRepository;
use Doctrine\DBAL\Connection;

final class DbalBookingRepository implements BookingRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function existsForSlot(int $slotId): bool
    {
        $result = $this->connection->fetchOne(
            "SELECT 1 FROM bookings WHERE slot_id = :slot LIMIT 1", [
                'slot' => $slotId
            ]
        );
        return $result !== false;
    }

    public function save(Booking $booking): int
    {
        $this->connection->executeStatement(
            "INSERT INTO bookings(customer_id, slot_id, created_at)
VALUES(:customer_id, :slot_id, :created_at)", [
                'customer_id' => $booking->customerId(),
                'slot_id' => $booking->slotId(),
                'created_at' => $booking->createdAt()->format('Y-m-d H:i:s')
            ]
        );
        return (int) $this->connection->lastInsertId();
    }
}

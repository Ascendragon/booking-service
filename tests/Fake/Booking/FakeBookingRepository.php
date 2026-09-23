<?php

declare(strict_types=1);

namespace App\Tests\Fake\Booking;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingRepository;

final class FakeBookingRepository implements BookingRepository
{
    private array $bookings = [];
    public function existsForSlot(int $slotId): bool
    {
        if(isset($this->bookings[$slotId])) {
            return true;
        }
        return false;
    }

    public function save(Booking $booking): int
    {
        $id = count($this->bookings) + 1;

        $this->bookings[$booking->slotId()] = $booking;

        return $id;
    }
}

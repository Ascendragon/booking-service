<?php

namespace App\Booking\Domain;


interface BookingRepository
{
    public function existsForSlot(int $slotId): bool;
    public function save(Booking $booking): int;
}

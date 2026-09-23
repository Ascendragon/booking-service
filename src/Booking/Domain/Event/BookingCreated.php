<?php

namespace App\Booking\Domain\Event;

final readonly class BookingCreated
{
    public function __construct(
        public int $bookingId,
        public int $slotId,
        public int $customerId,
    ) {
    }

    public function eventName(): string
    {
        return 'BookingCreated';
    }

    public function payload(): array
    {
        return [
            'bookingId' => $this->bookingId,
            'slotId' => $this->slotId,
            'customerId' => $this->customerId,
        ];
    }
}

<?php

namespace App\Booking\Domain\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final readonly class BookingCreated implements DomainEvent
{
    public function __construct(
        public int $bookingId,
        public int $slotId,
        public int $customerId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventType(): string
    {
        return 'booking.created.v1';
    }

    public function payload(): array
    {
        return [
            'bookingId' => $this->bookingId,
            'slotId' => $this->slotId,
            'customerId' => $this->customerId,
        ];
    }
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
    public function aggregateId(): int
    {
        return $this->bookingId;
    }
    public function aggregateType(): string
    {
        return 'Booking';
    }
}

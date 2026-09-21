<?php
declare(strict_types = 1);

namespace App\Booking\Domain;

use DateTimeImmutable;

final class Booking
{
    public function __construct(
        private ?int $id,
        private int $slotId,
        private int $customerId,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $slotId,
        int $customerId,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: null,
            slotId: $slotId,
            customerId: $customerId,
            createdAt: $createdAt,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function slotId(): int
    {
        return $this->slotId;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

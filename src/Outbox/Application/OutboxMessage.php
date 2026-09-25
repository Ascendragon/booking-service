<?php

declare(strict_types=1);

namespace App\Outbox\Application;

use DateTimeImmutable;

final readonly class OutboxMessage
{
    public function __construct(
        public int $id,
        public string $eventId,
        public string $eventType,
        public string $aggregateType,
        public int $aggregateId,
        public array $payload,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}

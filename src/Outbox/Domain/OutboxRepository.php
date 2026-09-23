<?php

namespace App\Outbox\Domain;

interface OutboxRepository
{
    public function save(
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        array $payload
    ): void;
}

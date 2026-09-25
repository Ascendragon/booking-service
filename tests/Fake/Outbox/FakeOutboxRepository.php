<?php

declare(strict_types=1);

namespace App\Tests\Fake\Outbox;

use App\Outbox\Domain\OutboxRepository;

final class FakeOutboxRepository implements OutboxRepository
{
    public array $events = [];

    public function save(
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        array $payload,
    ): void {
        $this->events[] = [
            'eventType' => $eventType,
            'aggregateType' => $aggregateType,
            'aggregateId' => $aggregateId,
            'payload' => $payload,
        ];
    }
}

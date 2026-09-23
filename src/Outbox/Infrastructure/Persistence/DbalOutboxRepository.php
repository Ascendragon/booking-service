<?php

declare(strict_types=1);

namespace App\Outbox\Infrastructure\Persistence;

use App\Outbox\Domain\OutboxRepository;
use App\Shared\Clock;
use Doctrine\DBAL\Connection;

final class DbalOutboxRepository implements OutboxRepository
{
    public function __construct(
        private Connection $connection,
        private Clock $clock
    ) {
    }

    public function save(
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        array $payload
    ): void {
        $this->connection->insert(
            'outbox_events',
            [
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => $this->clock
                    ->now()
                    ->format('Y-m-d H:i:s')
            ]
        );
    }
}

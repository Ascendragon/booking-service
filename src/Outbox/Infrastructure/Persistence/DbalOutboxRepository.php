<?php

declare(strict_types=1);

namespace App\Outbox\Infrastructure\Persistence;

use App\Outbox\Domain\OutboxRepository;
use App\Shared\Domain\DomainEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final class DbalOutboxRepository implements OutboxRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function save(
        DomainEvent $event,
    ): void {
        $this->connection->insert(
            'outbox_events',
            [
                'event_id' => Uuid::v7()->toRfc4122(),
                'event_type' => $event->eventType(),
                'aggregate_type' => $event->aggregateType(),
                'aggregate_id' => $event->aggregateId(),
                'payload' => json_encode($event->payload(), JSON_THROW_ON_ERROR),
                'occurred_at' => $event->occurredAt()
                    ->format('Y-m-d H:i:s')
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Fake\Outbox;

use App\Outbox\Domain\OutboxRepository;
use App\Shared\Domain\DomainEvent;

final class FakeOutboxRepository implements OutboxRepository
{
    public array $events = [];

    public function save(DomainEvent $event): void
    {
        $this->events[] = $event;
    }
}

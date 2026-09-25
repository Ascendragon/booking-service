<?php

namespace App\Outbox\Domain;

use App\Shared\Domain\DomainEvent;

interface OutboxRepository
{
    public function save(
        DomainEvent $event,
    ): void;
}

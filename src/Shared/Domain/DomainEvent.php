<?php

namespace App\Shared\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function eventType(): string;

    public function aggregateType(): string;

    public function aggregateId(): int;

    public function occurredAt(): DateTimeImmutable;

    public function payload(): array;
}

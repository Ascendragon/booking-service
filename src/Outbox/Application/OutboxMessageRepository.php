<?php

declare(strict_types=1);

namespace App\Outbox\Application;

use DateTimeImmutable;

interface OutboxMessageRepository
{
    /**
     * @return list<OutboxMessage>
     */
    public function findPendingBatch(int $limit): array;

    /**
     * @return list<OutboxMessage>
     */
    /**
     * @return list<OutboxMessage>
     */
    public function claimPendingBatch(
        int $limit,
        string $claimToken,
        DateTimeImmutable $claimedAt,
        DateTimeImmutable $staleBefore,
    ): array;

    public function markProcessed(
        int $id,
        string $claimToken,
        DateTimeImmutable $processedAt,
    ): bool;
}

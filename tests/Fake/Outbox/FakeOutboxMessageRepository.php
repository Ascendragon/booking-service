<?php

declare(strict_types=1);

namespace App\Tests\Fake\Outbox;

use App\Outbox\Application\OutboxMessage;
use App\Outbox\Application\OutboxMessageRepository;
use DateTimeImmutable;

final class FakeOutboxMessageRepository implements OutboxMessageRepository
{
    /** @var list<OutboxMessage> */
    public array $messagesToClaim = [];

    /** @var list<array{
     *     limit: int,
     *     claimToken: string,
     *     claimedAt: DateTimeImmutable,
     *     staleBefore: DateTimeImmutable
     * }>
     */
    public array $claimCalls = [];

    /** @var list<array{
     *     id: int,
     *     claimToken: string,
     *     processedAt: DateTimeImmutable
     * }>
     */
    public array $markProcessedCalls = [];

    public bool $markProcessedResult = true;

    public function findPendingBatch(int $limit): array
    {
        return array_slice(
            $this->messagesToClaim,
            0,
            $limit,
        );
    }

    public function claimPendingBatch(
        int $limit,
        string $claimToken,
        DateTimeImmutable $claimedAt,
        DateTimeImmutable $staleBefore,
    ): array {
        $this->claimCalls[] = [
            'limit' => $limit,
            'claimToken' => $claimToken,
            'claimedAt' => $claimedAt,
            'staleBefore' => $staleBefore,
        ];

        return array_slice(
            $this->messagesToClaim,
            0,
            $limit,
        );
    }

    public function markProcessed(
        int $id,
        string $claimToken,
        DateTimeImmutable $processedAt,
    ): bool {
        $this->markProcessedCalls[] = [
            'id' => $id,
            'claimToken' => $claimToken,
            'processedAt' => $processedAt,
        ];

        return $this->markProcessedResult;
    }
}

<?php

declare(strict_types=1);

namespace App\Outbox\Application;

use App\Outbox\Application\Exception\OutboxOwnershipLost;
use App\Shared\Clock;

final class OutboxPublisher
{
    public function __construct(
        private OutboxMessageRepository $repository,
        private MessagePublisher $publisher,
        private Clock $clock,
        private int $leaseSeconds = 300,
    ) {
        if ($leaseSeconds < 1) {
            throw new \InvalidArgumentException(
                'Lease duration must be greater than zero.'
            );
        }
    }

    public function publishBatch(
        int $limit,
        string $claimToken,
    ): int
    {
        $now = $this->clock->now();

        $staleBefore = $now->modify(sprintf('-%d seconds', $this->leaseSeconds));

        $messages = $this->repository->claimPendingBatch(
            limit: $limit,
            claimToken: $claimToken,
            claimedAt: $now,
            staleBefore: $staleBefore,
        );

        $processed = 0;

        foreach ($messages as $message) {
            $this->publisher->publish($message);

            $marked = $this->repository->markProcessed(
                id: $message->id,
                claimToken: $claimToken,
                processedAt: $this->clock->now(),
            );

            if (!$marked) {
                throw OutboxOwnershipLost::forMessage(
                    $message->id
                );
            }

            ++$processed;
        }
        return $processed;
    }
}

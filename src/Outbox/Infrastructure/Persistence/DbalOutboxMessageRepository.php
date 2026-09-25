<?php

namespace App\Outbox\Infrastructure\Persistence;

use App\Outbox\Application\OutboxMessage;
use App\Outbox\Application\OutboxMessageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use JsonException;
use Throwable;

class DbalOutboxMessageRepository implements OutboxMessageRepository
{

    public function __construct(
        private Connection $connection,
    )
    {
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function findPendingBatch(int $limit): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException(
                'Limit must be greater than zero.'
            );
        }
        $rows = $this->connection->fetchAllAssociative('
        SELECT
        id,
        event_id,
        event_type,
        aggregate_type,
        aggregate_id,
        payload,
        occurred_at
        FROM outbox_events
        WHERE processed_at IS NULL
        ORDER BY id
        LIMIT ?
        ', [$limit],
            [ParameterType::INTEGER]);

        return $this->mapRows($rows);
    }

    /**
     * @param int $limit
     * @param string $claimToken
     * @param DateTimeImmutable $claimedAt
     * @param DateTimeImmutable $staleBefore
     * @return array
     * @throws Exception
     * @throws JsonException
     * @throws Throwable
     */
    public function claimPendingBatch(int $limit, string $claimToken, \DateTimeImmutable $claimedAt, DateTimeImmutable $staleBefore): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException(
                'Limit must be greater than zero.'
            );
        }

        if ($claimToken === '') {
            throw new \InvalidArgumentException(
                'Claim token must not be empty.'
            );
        }

        $this->connection->beginTransaction();

        try {
            $rows = $this->connection->fetchAllAssociative(
                '
            SELECT
                id,
                event_id,
                event_type,
                aggregate_type,
                aggregate_id,
                payload,
                occurred_at
            FROM outbox_events
            WHERE processed_at IS NULL
              AND (
                  claimed_at IS NULL
                  OR claimed_at < :staleBefore
              )
            ORDER BY id
            LIMIT :limit
            FOR UPDATE SKIP LOCKED
            ',
                [
                    'staleBefore' => $staleBefore->format('Y-m-d H:i:s'),
                    'limit' => $limit,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ],
            );

            if ($rows === []) {
                $this->connection->commit();

                return [];
            }

            $ids = array_map(
                static fn(array $row): int => (int)$row['id'],
                $rows,
            );

            $this->connection->executeStatement(
                '
            UPDATE outbox_events
            SET claim_token = :claimToken,
                claimed_at = :claimedAt
            WHERE id IN (:ids)
            ',
                [
                    'claimToken' => $claimToken,
                    'claimedAt' => $claimedAt->format('Y-m-d H:i:s'),
                    'ids' => $ids,
                ],
                [
                    'ids' => ArrayParameterType::INTEGER,
                ],
            );

            $this->connection->commit();

            return $this->mapRows($rows);


        } catch (Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<OutboxMessage>
     */
    private function mapRows(array $rows): array
    {
        return array_map(
            static fn(array $row): OutboxMessage => new OutboxMessage(
                id: (int)$row['id'],
                eventId: $row['event_id'],
                eventType: $row['event_type'],
                aggregateType: $row['aggregate_type'],
                aggregateId: (int)$row['aggregate_id'],
                payload: json_decode(
                    $row['payload'],
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
                occurredAt: new DateTimeImmutable(
                    $row['occurred_at']
                ),
            ),
            $rows,
        );
    }


    public function markProcessed(
        int $id,
        string $claimToken,
        DateTimeImmutable $processedAt,
    ): bool {
        if ($claimToken === '') {
            throw new \InvalidArgumentException(
                'Claim token must not be empty.'
            );
        }

        $affectedRows = $this->connection->executeStatement(
            '
        UPDATE outbox_events
        SET processed_at = :processedAt,
            claim_token = NULL,
            claimed_at = NULL
        WHERE id = :id
          AND claim_token = :claimToken
          AND processed_at IS NULL
        ',
            [
                'id' => $id,
                'claimToken' => $claimToken,
                'processedAt' => $processedAt->format('Y-m-d H:i:s'),
            ],
            [
                'id' => ParameterType::INTEGER,
            ],
        );

        return $affectedRows === 1;
    }
}

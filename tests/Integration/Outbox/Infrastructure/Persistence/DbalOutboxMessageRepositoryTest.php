<?php

declare(strict_types=1);

namespace App\Tests\Integration\Outbox\Infrastructure\Persistence;

use App\Outbox\Application\OutboxMessage;
use App\Outbox\Infrastructure\Persistence\DbalOutboxMessageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalOutboxMessageRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private DbalOutboxMessageRepository $repository;
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);

        $this->connection->delete('outbox_events');

        $this->repository = new DbalOutboxMessageRepository($this->connection);

    }

    public function testFindsPendingBatchOrderedByIdWithLimit(): void
    {
        $firstId = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
            aggregateId: 101,
            slotId: 10
        );

        $secondId = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000002',
            aggregateId: 102,
            slotId: 20,
        );

        $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000003',
            aggregateId: 103,
            slotId: 30,
            processedAt: '2030-01-01 12:00:00',
        );

        $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000004',
            aggregateId: 104,
            slotId: 40,
        );

        $messages = $this->repository->findPendingBatch(2);

        self::assertCount(2, $messages);

        self::assertSame($firstId, $messages[0]->id);
        self::assertSame($secondId, $messages[1]->id);

        self::assertSame(
            '00000000-0000-7000-8000-000000000001',
            $messages[0]->eventId,
        );

        self::assertSame(
            'booking.created.v1',
            $messages[0]->eventType,
        );

        self::assertSame(
            'Booking',
            $messages[0]->aggregateType,
        );

        self::assertSame(
            101,
            $messages[0]->aggregateId,
        );

        self::assertEquals(
            [
                'bookingId' => 101,
                'slotId' => 10,
                'customerId' => 500,
            ],
            $messages[0]->payload,
        );

        self::assertSame(
            '2030-01-01 10:00:00',
            $messages[0]->occurredAt->format('Y-m-d H:i:s'),
        );
    }

    private function insertEvent(
        string $eventId,
        int $aggregateId,
        int $slotId,
        ?string $processedAt = null,
        ?string $claimedAt = null,
        ?string $claimToken = null,
    ): int {
        $this->connection->insert(
            'outbox_events',
            [
                'event_id' => $eventId,
                'event_type' => 'booking.created.v1',
                'aggregate_type' => 'Booking',
                'aggregate_id' => $aggregateId,
                'payload' => json_encode(
                    [
                        'bookingId' => $aggregateId,
                        'slotId' => $slotId,
                        'customerId' => 500,
                    ],
                    JSON_THROW_ON_ERROR,
                ),
                'occurred_at' => '2030-01-01 10:00:00',
                'processed_at' => $processedAt,
                'claimed_at' => $claimedAt,
                'claim_token' => $claimToken,
            ],
        );

        return (int) $this->connection->lastInsertId();
    }

    public function testRejectsNonPositiveLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Limit must be greater than zero.'
        );

        $this->repository->findPendingBatch(0);
    }

    public function testClaimsPendingBatch(): void
    {
        $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
            aggregateId: 101,
            slotId: 10,
        );

        $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000002',
            aggregateId: 102,
            slotId: 20,
        );

        $claimedAt = new DateTimeImmutable(
            '2030-01-01 11:00:00'
        );

        $messages = $this->repository->claimPendingBatch(
            limit: 2,
            claimToken: 'worker-1',
            claimedAt: $claimedAt,
            staleBefore: new DateTimeImmutable('2030-01-01 09:55:00')
        );

        self::assertCount(2, $messages);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT claim_token, claimed_at
         FROM outbox_events
         ORDER BY id'
        );

        self::assertSame(
            'worker-1',
            $rows[0]['claim_token'],
        );

        self::assertSame(
            '2030-01-01 11:00:00',
            $rows[0]['claimed_at'],
        );

        self::assertSame(
            'worker-1',
            $rows[1]['claim_token'],
        );

        $secondClaim = $this->repository->claimPendingBatch(
            limit: 2,
            claimToken: 'worker-2',
            claimedAt: $claimedAt,
            staleBefore: new DateTimeImmutable('2030-01-01 09:55:00')
        );

        self::assertCount(0, $secondClaim);
    }

    public function testSkipsRowsLockedByAnotherWorker(): void
    {
        $firstId = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
            aggregateId: 101,
            slotId: 10,
        );

        $secondId = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000002',
            aggregateId: 102,
            slotId: 20,
        );

        $thirdId = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000003',
            aggregateId: 103,
            slotId: 30,
        );

        $fourthId = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000004',
            aggregateId: 104,
            slotId: 40,
        );

        $connectionA = $this->connection;

        $connectionB = DriverManager::getConnection(
            $this->connection->getParams()
        );

        $repositoryB = new DbalOutboxMessageRepository(
            $connectionB
        );

        $connectionA->beginTransaction();

        try {
            $lockedIds = $connectionA->fetchFirstColumn(
                '
            SELECT id
            FROM outbox_events
            WHERE processed_at IS NULL
              AND claimed_at IS NULL
            ORDER BY id
            LIMIT 2
            FOR UPDATE
            '
            );

            self::assertSame(
                [$firstId, $secondId],
                array_map('intval', $lockedIds),
            );

            $messages = $repositoryB->claimPendingBatch(
                limit: 2,
                claimToken: 'worker-b',
                claimedAt: new DateTimeImmutable(
                    '2030-01-01 11:00:00',
                ),
                staleBefore: new DateTimeImmutable('2030-01-01 09:55:00')
            );
            self::assertCount(2, $messages);

            self::assertSame(
                [$thirdId, $fourthId],
                array_map(
                    static fn(OutboxMessage $message): int => $message->id,
                    $messages,
                ),
            );
        } finally {
            $connectionA->rollBack();
            $connectionB->close();
        }
    }

    public function testDoesNotReclaimFreshClaim(): void
    {
        $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
            aggregateId: 101,
            slotId: 10,
            claimedAt: '2030-01-01 11:58:00',
            claimToken: 'worker-a',
        );

        $messages = $this->repository->claimPendingBatch(
            limit: 10,
            claimToken: 'worker-b',
            claimedAt: new \DateTimeImmutable('2030-01-01 12:00:00'),
            staleBefore: new \DateTimeImmutable('2030-01-01 11:55:00'),
        );

        self::assertCount(0, $messages);
    }

    public function testReclaimsExpiredClaim(): void
    {
        $eventId = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
            aggregateId: 101,
            slotId: 10,
            claimedAt: '2030-01-01 11:50:00',
            claimToken: 'worker-a',
        );

        $messages = $this->repository->claimPendingBatch(
            limit: 10,
            claimToken: 'worker-b',
            claimedAt: new \DateTimeImmutable('2030-01-01 12:00:00'),
            staleBefore: new \DateTimeImmutable('2030-01-01 11:55:00'),
        );

        self::assertCount(1, $messages);
        self::assertSame($eventId, $messages[0]->id);

        $row = $this->connection->fetchAssociative(
            'SELECT claim_token, claimed_at
         FROM outbox_events
         WHERE id = ?',
            [$eventId],
        );

        self::assertIsArray($row);
        self::assertSame('worker-b', $row['claim_token']);
        self::assertSame(
            '2030-01-01 12:00:00',
            $row['claimed_at'],
        );
    }

    public function testOwnerCanMarkMessageAsProcessed(): void
    {
        $id = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
            aggregateId: 101,
            slotId: 10,
            claimedAt: '2030-01-01 12:00:00',
            claimToken: 'worker-a',
        );

        $result = $this->repository->markProcessed(
            id: $id,
            claimToken: 'worker-a',
            processedAt: new \DateTimeImmutable(
                '2030-01-01 12:01:00'
            ),
        );

        self::assertTrue($result);

        $row = $this->connection->fetchAssociative(
            'SELECT processed_at, claimed_at, claim_token
         FROM outbox_events
         WHERE id = ?',
            [$id],
        );

        self::assertIsArray($row);

        self::assertSame(
            '2030-01-01 12:01:00',
            $row['processed_at'],
        );

        self::assertNull($row['claimed_at']);
        self::assertNull($row['claim_token']);
    }

    public function testPreviousOwnerCannotMarkReclaimedMessageAsProcessed(): void
    {
        $id = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
            aggregateId: 101,
            slotId: 10,
            claimedAt: '2030-01-01 12:00:00',
            claimToken: 'worker-b',
        );

        $result = $this->repository->markProcessed(
            id: $id,
            claimToken: 'worker-a',
            processedAt: new \DateTimeImmutable(
                '2030-01-01 12:01:00'
            ),
        );

        self::assertFalse($result);

        $row = $this->connection->fetchAssociative(
            'SELECT processed_at, claim_token
         FROM outbox_events
         WHERE id = ?',
            [$id],
        );

        self::assertIsArray($row);
        self::assertNull($row['processed_at']);
        self::assertSame('worker-b', $row['claim_token']);
    }


}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Outbox\Application;

use App\Outbox\Application\Exception\OutboxOwnershipLost;
use App\Outbox\Application\OutboxMessageRepository;
use App\Outbox\Application\OutboxPublisher;
use App\Outbox\Infrastructure\Persistence\DbalOutboxMessageRepository;
use App\Shared\Clock;
use App\Tests\Fake\Outbox\FakeMessagePublisher;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OutboxPublisherIntegrationTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);

        $this->connection->executeStatement(
            'DELETE FROM outbox_events'
        );
    }

    #[Test]
    public function itPublishesPendingMessageAndMarksItAsProcessed(): void
    {
        $id = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
        );

        $repository = new DbalOutboxMessageRepository(
            $this->connection,
        );

        $broker = new FakeMessagePublisher();

        $clock = $this->fixedClock(
            new DateTimeImmutable('2030-01-01 12:00:00')
        );

        $publisher = new OutboxPublisher(
            repository: $repository,
            publisher: $broker,
            clock: $clock,
            leaseSeconds: 300,
        );

        $processed = $publisher->publishBatch(
            limit: 10,
            claimToken: 'worker-a',
        );

        self::assertSame(1, $processed);

        self::assertCount(1, $broker->published);

        self::assertSame(
            $id,
            $broker->published[0]->id,
        );

        self::assertSame(
            '00000000-0000-7000-8000-000000000001',
            $broker->published[0]->eventId,
        );

        $row = $this->connection->fetchAssociative(
            '
            SELECT processed_at, claimed_at, claim_token
            FROM outbox_events
            WHERE id = ?
            ',
            [$id],
        );

        self::assertIsArray($row);

        self::assertSame(
            '2030-01-01 12:00:00',
            $row['processed_at'],
        );

        self::assertNull($row['claimed_at']);
        self::assertNull($row['claim_token']);
    }

    private function insertEvent(string $eventId): int
    {
        $this->connection->insert(
            'outbox_events',
            [
                'event_id' => $eventId,
                'event_type' => 'booking.created.v1',
                'aggregate_type' => 'Booking',
                'aggregate_id' => 101,
                'payload' => json_encode(
                    [
                        'bookingId' => 101,
                        'slotId' => 10,
                        'customerId' => 500,
                    ],
                    JSON_THROW_ON_ERROR,
                ),
                'occurred_at' => '2030-01-01 10:00:00',
                'processed_at' => null,
                'claimed_at' => null,
                'claim_token' => null,
            ],
        );

        return (int) $this->connection->lastInsertId();
    }

    private function fixedClock(
        DateTimeImmutable $now,
    ): Clock {
        return new class($now) implements Clock {
            public function __construct(
                private DateTimeImmutable $now,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    #[Test]
    public function itKeepsMessageUnprocessedWhenBrokerPublishFails(): void
    {
        $id = $this->insertEvent(
            eventId: '00000000-0000-7000-8000-000000000001',
        );

        $repository = new DbalOutboxMessageRepository(
            $this->connection,
        );

        $broker = new FakeMessagePublisher();
        $broker->failOnMessageId = $id;

        $clock = $this->fixedClock(
            new DateTimeImmutable('2030-01-01 12:00:00')
        );

        $publisher = new OutboxPublisher(
            repository: $repository,
            publisher: $broker,
            clock: $clock,
            leaseSeconds: 300,
        );

        try {
            $publisher->publishBatch(
                limit: 10,
                claimToken: 'worker-a',
            );

            self::fail('Expected broker failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'Broker unavailable.',
                $exception->getMessage(),
            );
        }

        $row = $this->connection->fetchAssociative(
            '
        SELECT processed_at, claimed_at, claim_token
        FROM outbox_events
        WHERE id = ?
        ',
            [$id],
        );

        self::assertIsArray($row);

        self::assertNull(
            $row['processed_at']
        );

        self::assertSame(
            '2030-01-01 12:00:00',
            $row['claimed_at'],
        );

        self::assertSame(
            'worker-a',
            $row['claim_token'],
        );
    }

    #[Test]
    public function itMayPublishSameEventAgainWhenAcknowledgementFails(): void
    {
        $eventId = '00000000-0000-7000-8000-000000000001';

        $id = $this->insertEvent(
            eventId: $eventId,
        );

        $realRepository = new DbalOutboxMessageRepository(
            $this->connection,
        );

        /*
         * Симулируем ситуацию:
         *
         * broker уже получил message,
         * но acknowledgement в БД не состоялся.
         *
         * claim работает через настоящий DBAL repository,
         * а markProcessed() возвращает false.
         */
        $repositoryWithFailedAcknowledgement =
            new class($realRepository) implements OutboxMessageRepository {
                public function __construct(
                    private OutboxMessageRepository $repository,
                ) {
                }

                public function findPendingBatch(int $limit): array
                {
                    return $this->repository->findPendingBatch($limit);
                }

                public function claimPendingBatch(
                    int $limit,
                    string $claimToken,
                    DateTimeImmutable $claimedAt,
                    DateTimeImmutable $staleBefore,
                ): array {
                    return $this->repository->claimPendingBatch(
                        limit: $limit,
                        claimToken: $claimToken,
                        claimedAt: $claimedAt,
                        staleBefore: $staleBefore,
                    );
                }

                public function markProcessed(
                    int $id,
                    string $claimToken,
                    DateTimeImmutable $processedAt,
                ): bool {
                    return false;
                }
            };

        $broker = new FakeMessagePublisher();

        /*
         * Первая попытка — 12:00.
         */
        $firstPublisher = new OutboxPublisher(
            repository: $repositoryWithFailedAcknowledgement,
            publisher: $broker,
            clock: $this->fixedClock(
                new DateTimeImmutable('2030-01-01 12:00:00')
            ),
            leaseSeconds: 300,
        );

        try {
            $firstPublisher->publishBatch(
                limit: 10,
                claimToken: 'worker-a',
            );

            self::fail(
                'Expected ownership loss exception.'
            );
        } catch (OutboxOwnershipLost) {
            // Expected.
        }

        /*
         * В broker событие уже ушло.
         */
        self::assertCount(1, $broker->published);

        self::assertSame(
            $eventId,
            $broker->published[0]->eventId,
        );

        /*
         * Но БД считает его незавершённым.
         */
        $row = $this->connection->fetchAssociative(
            '
        SELECT processed_at, claimed_at, claim_token
        FROM outbox_events
        WHERE id = ?
        ',
            [$id],
        );

        self::assertIsArray($row);
        self::assertNull($row['processed_at']);

        self::assertSame(
            '2030-01-01 12:00:00',
            $row['claimed_at'],
        );

        self::assertSame(
            'worker-a',
            $row['claim_token'],
        );

        /*
         * Прошло 6 минут.
         *
         * Lease = 5 минут,
         * поэтому claim worker-a уже протух.
         */
        $secondPublisher = new OutboxPublisher(
            repository: $realRepository,
            publisher: $broker,
            clock: $this->fixedClock(
                new DateTimeImmutable('2030-01-01 12:06:00')
            ),
            leaseSeconds: 300,
        );

        $processed = $secondPublisher->publishBatch(
            limit: 10,
            claimToken: 'worker-b',
        );

        self::assertSame(1, $processed);

        /*
         * Broker получил два сообщения.
         */
        self::assertCount(2, $broker->published);

        /*
         * Но это одно и то же logical event.
         */
        self::assertSame(
            $eventId,
            $broker->published[0]->eventId,
        );

        self::assertSame(
            $eventId,
            $broker->published[1]->eventId,
        );

        self::assertSame(
            $broker->published[0]->eventId,
            $broker->published[1]->eventId,
        );

        /*
         * Вторая попытка уже успешно подтверждена в БД.
         */
        $row = $this->connection->fetchAssociative(
            '
        SELECT processed_at, claimed_at, claim_token
        FROM outbox_events
        WHERE id = ?
        ',
            [$id],
        );

        self::assertIsArray($row);

        self::assertSame(
            '2030-01-01 12:06:00',
            $row['processed_at'],
        );

        self::assertNull($row['claimed_at']);
        self::assertNull($row['claim_token']);
    }
}

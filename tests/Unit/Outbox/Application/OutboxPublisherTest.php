<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbox\Application;

use App\Outbox\Application\Exception\OutboxOwnershipLost;
use App\Outbox\Application\OutboxMessage;
use App\Outbox\Application\OutboxPublisher;
use App\Shared\Clock;
use App\Tests\Fake\Outbox\FakeMessagePublisher;
use App\Tests\Fake\Outbox\FakeOutboxMessageRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OutboxPublisherTest extends TestCase
{
    public function testPublishesClaimedMessagesAndMarksThemAsProcessed(): void
    {
        $repository = new FakeOutboxMessageRepository();

        $repository->messagesToClaim = [
            $this->message(
                id: 1,
                eventId: '00000000-0000-7000-8000-000000000001',
            ),
            $this->message(
                id: 2,
                eventId: '00000000-0000-7000-8000-000000000002',
            ),
        ];

        $messagePublisher = new FakeMessagePublisher();

        $now = new DateTimeImmutable(
            '2030-01-01 12:00:00'
        );

        $clock = new class($now) implements Clock {
            public function __construct(
                private DateTimeImmutable $now,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $publisher = new OutboxPublisher(
            repository: $repository,
            publisher: $messagePublisher,
            clock: $clock,
            leaseSeconds: 300,
        );

        $processed = $publisher->publishBatch(
            limit: 10,
            claimToken: 'worker-a',
        );

        self::assertSame(2, $processed);

        self::assertCount(
            2,
            $messagePublisher->published,
        );

        self::assertSame(
            1,
            $messagePublisher->published[0]->id,
        );

        self::assertSame(
            2,
            $messagePublisher->published[1]->id,
        );

        self::assertCount(
            1,
            $repository->claimCalls,
        );

        self::assertSame(
            10,
            $repository->claimCalls[0]['limit'],
        );

        self::assertSame(
            'worker-a',
            $repository->claimCalls[0]['claimToken'],
        );

        self::assertEquals(
            new DateTimeImmutable('2030-01-01 11:55:00'),
            $repository->claimCalls[0]['staleBefore'],
        );

        self::assertCount(
            2,
            $repository->markProcessedCalls,
        );

        self::assertSame(
            1,
            $repository->markProcessedCalls[0]['id'],
        );

        self::assertSame(
            'worker-a',
            $repository->markProcessedCalls[0]['claimToken'],
        );
    }

    private function message(
        int $id,
        string $eventId,
    ): OutboxMessage {
        return new OutboxMessage(
            id: $id,
            eventId: $eventId,
            eventType: 'booking.created.v1',
            aggregateType: 'Booking',
            aggregateId: 100 + $id,
            payload: [
                'bookingId' => 100 + $id,
                'slotId' => 10 + $id,
                'customerId' => 500,
            ],
            occurredAt: new DateTimeImmutable(
                '2030-01-01 10:00:00'
            ),
        );
    }

    public function testDoesNothingWhenThereAreNoMessages(): void
    {
        $repository = new FakeOutboxMessageRepository();
        $messagePublisher = new FakeMessagePublisher();

        $now = new DateTimeImmutable('2030-01-01 12:00:00');

        $clock = new class($now) implements Clock {
            public function __construct(
                private DateTimeImmutable $now,
            ){}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $publisher = new OutboxPublisher(
            repository: $repository,
            publisher: $messagePublisher,
            clock: $clock,
            leaseSeconds: 300,
        );

        $processed = $publisher->publishBatch(
            limit: 10,
            claimToken: 'worker-a',
        );

        self::assertSame(0, $processed);
        self::assertCount(0, $messagePublisher->published);
        self::assertCount(0, $repository->markProcessedCalls);

        // Claim мы всё равно должны были попытаться выполнить.
        self::assertCount(1, $repository->claimCalls);
    }

    public function testDoesNotMarkMessageAsProcessedWhenBrokerPublishFails(): void
    {
        $repository = new FakeOutboxMessageRepository();

        $repository->messagesToClaim = [
            $this->message(
                id: 1,
                eventId: '00000000-0000-7000-8000-000000000001',
            ),
        ];

        $messagePublisher = new FakeMessagePublisher();
        $messagePublisher->failOnMessageId = 1;

        $now = new DateTimeImmutable('2030-01-01 12:00:00');

        $clock = new class($now) implements Clock {
            public function __construct(
                private DateTimeImmutable $now,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $publisher = new OutboxPublisher(
            repository: $repository,
            publisher: $messagePublisher,
            clock: $clock,
            leaseSeconds: 300,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIs('Broker unavailable.');

        try {
            $publisher->publishBatch(
                limit: 10,
                claimToken: 'worker-a',
            );
        } finally {
            self::assertCount(
                0,
                $repository->markProcessedCalls,
            );
        }
    }

    public function testStopsBatchWhenBrokerPublishFails(): void
    {
        $repository = new FakeOutboxMessageRepository();

        $repository->messagesToClaim = [
            $this->message(
                id: 1,
                eventId: '00000000-0000-7000-8000-000000000001',
            ),
            $this->message(
                id: 2,
                eventId: '00000000-0000-7000-8000-000000000002',
            ),
            $this->message(
                id: 3,
                eventId: '00000000-0000-7000-8000-000000000003',
            ),
        ];

        $messagePublisher = new FakeMessagePublisher();
        $messagePublisher->failOnMessageId = 2;

        $now = new DateTimeImmutable('2030-01-01 12:00:00');

        $clock = new class($now) implements Clock {
            public function __construct(
                private DateTimeImmutable $now,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $publisher = new OutboxPublisher(
            repository: $repository,
            publisher: $messagePublisher,
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

        self::assertCount(1, $messagePublisher->published);
        self::assertSame(
            1,
            $messagePublisher->published[0]->id,
        );

        self::assertCount(
            1,
            $repository->markProcessedCalls,
        );

        self::assertSame(
            1,
            $repository->markProcessedCalls[0]['id'],
        );
    }

    public function testThrowsWhenOwnershipIsLostAfterPublishing(): void
    {
        $repository = new FakeOutboxMessageRepository();

        $repository->messagesToClaim = [
            $this->message(
                id: 1,
                eventId: '00000000-0000-7000-8000-000000000001',
            ),
        ];

        $repository->markProcessedResult = false;

        $messagePublisher = new FakeMessagePublisher();

        $now = new DateTimeImmutable(
            '2030-01-01 12:00:00'
        );

        $clock = new class($now) implements Clock {
            public function __construct(
                private DateTimeImmutable $now,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $publisher = new OutboxPublisher(
            repository: $repository,
            publisher: $messagePublisher,
            clock: $clock,
            leaseSeconds: 300,
        );

        try {
            $publisher->publishBatch(
                limit: 10,
                claimToken: 'worker-a',
            );

            self::fail(
                'Expected ownership loss exception.'
            );
        } catch (OutboxOwnershipLost $exception) {
            self::assertSame(
                'Ownership lost for outbox message 1.',
                $exception->getMessage(),
            );
        }

        self::assertCount(
            1,
            $messagePublisher->published,
        );

        self::assertSame(
            1,
            $messagePublisher->published[0]->id,
        );

        self::assertCount(
            1,
            $repository->markProcessedCalls,
        );
    }

    public function testRejectsNonPositiveLeaseDuration(): void
    {
        $repository = new FakeOutboxMessageRepository();
        $messagePublisher = new FakeMessagePublisher();

        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(
                    '2030-01-01 12:00:00'
                );
            }
        };

        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->expectExceptionMessageIs(
            'Lease duration must be greater than zero.'
        );

        new OutboxPublisher(
            repository: $repository,
            publisher: $messagePublisher,
            clock: $clock,
            leaseSeconds: 0,
        );
    }
}

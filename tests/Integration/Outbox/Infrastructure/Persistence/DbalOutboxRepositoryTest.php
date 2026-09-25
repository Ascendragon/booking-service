<?php

declare(strict_types=1);

namespace App\Tests\Integration\Outbox\Infrastructure\Persistence;


use App\Booking\Domain\Event\BookingCreated;
use App\Outbox\Infrastructure\Persistence\DbalOutboxRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalOutboxRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private DbalOutboxRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->connection = self::getContainer()
            ->get(Connection::class);


        $this->repository = new DbalOutboxRepository(
            $this->connection
        );

        $this->connection->delete('outbox_events');
    }


    public function testSaveOutboxEvent(): void
    {
        $this->repository->save(
            new BookingCreated(
                bookingId: 123,
                slotId: 10,
                customerId: 100,
                occurredAt: new DateTimeImmutable('2030-01-01 10:00:00'),
            )
        );


        $event = $this->connection->fetchAssociative(
            'SELECT * FROM outbox_events LIMIT 1'
        );

        self::assertIsArray($event);

        self::assertTrue(
            Uuid::isValid($event['event_id'])
        );

        self::assertSame(
            'booking.created.v1',
            $event['event_type']
        );

        self::assertSame(
            'Booking',
            $event['aggregate_type']
        );

        self::assertSame(
            123,
            (int) $event['aggregate_id']
        );


        $payload = json_decode(
            $event['payload'],
            true,
            flags: JSON_THROW_ON_ERROR
        );


        self::assertSame(
            10,
            $payload['slotId']
        );

        self::assertSame(
            '2030-01-01 10:00:00',
            $event['occurred_at'],
        );
    }


}

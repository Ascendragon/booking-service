<?php

declare(strict_types=1);

namespace App\Tests\Integration\Outbox\Infrastructure\Persistence;

use App\Infrastructure\Clock\SystemClock;
use App\Outbox\Infrastructure\Persistence\DbalOutboxRepository;
use App\Shared\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
            $this->connection, new SystemClock()
        );

        $this->connection->delete('outbox_events');
    }


    public function testSaveOutboxEvent(): void
    {
        $this->repository->save(
            'BookingCreated',
            'Booking',
            123,
            [
                'bookingId' => 123,
                'slotId' => 10,
                'customerId' => 100,
            ]
        );


        $event = $this->connection->fetchAssociative(
            'SELECT * FROM outbox_events LIMIT 1'
        );


        self::assertSame(
            'BookingCreated',
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
            true
        );


        self::assertSame(
            10,
            $payload['slotId']
        );
    }
}

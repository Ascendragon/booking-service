<?php

declare(strict_types=1);

namespace App\Tests\Unit\Booking\Application\CreateBooking;

use App\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Booking\Domain\Event\BookingCreated;
use App\Booking\Domain\Booking;
use App\Booking\Domain\Exception\SlotAlreadyBooked;
use App\Tests\Fake\Booking\FakeBookingRepository;
use App\Tests\Fake\Outbox\FakeOutboxRepository;
use App\Tests\Fake\Shared\FakeClock;
use App\Tests\Fake\Shared\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

final class CreateBookingHandlerTest extends TestCase
{

    public function testCreateBookingForAvailableSlot() : void
    {
        $repository = new FakeBookingRepository();
        $clock = new FakeClock();
        $outbox = new FakeOutboxRepository();
        $transactionManager = new FakeTransactionManager();



        $handler = new CreateBookingHandler($repository, $clock, $transactionManager, $outbox);

        $command = new CreateBookingCommand(slotId: 1, customerId: 100);

        $handler($command);

        self::assertTrue(
            $repository->existsForSlot(1)
        );
        self::assertCount(1, $outbox->events);
    }

    public function testCreateBookingForUnavailableSlot(): void
    {
        $repository = new FakeBookingRepository();
        $clock = new FakeClock();
        $outbox = new FakeOutboxRepository();
        $transactionManager = new FakeTransactionManager();

        $existingBooking = new Booking(1, 1, 100, $clock->now());
        $repository->save($existingBooking);

        $handler = new CreateBookingHandler(
            $repository,
            $clock,
            $transactionManager,
            $outbox
        );

        $command = new CreateBookingCommand(
            slotId: 1,
            customerId: 200
        );

        $this->expectException(SlotAlreadyBooked::class);

        $handler($command);
    }
    public function testDoesNotCreateOutboxEventWhenSlotAlreadyBooked(): void
    {
        $repository = new FakeBookingRepository();
        $outbox = new FakeOutboxRepository();
        $transactionManager = new FakeTransactionManager();
        $clock = new FakeClock();

        $repository->save(
            Booking::create(
                slotId: 1,
                customerId: 100,
                createdAt: $clock->now(),
            )
        );

        $handler = new CreateBookingHandler(
            $repository,
            $clock,
            $transactionManager,
            $outbox,
        );

        try {
            $handler(
                new CreateBookingCommand(
                    slotId: 1,
                    customerId: 200,
                )
            );

            self::fail('Expected SlotAlreadyBooked');
        } catch (SlotAlreadyBooked) {
            self::assertCount(0, $outbox->events);
        }
    }

    public function testCreateBookingCreatesOutboxEvent(): void
    {
        $repository = new FakeBookingRepository();
        $outbox = new FakeOutboxRepository();
        $clock = new FakeClock();
        $handler = new CreateBookingHandler($repository, $clock, new FakeTransactionManager(),$outbox );

        $id = $handler(
            new CreateBookingCommand(
                slotId: 1,
                customerId: 100
            )
        );

        self::assertGreaterThan(0, $id);

        self::assertCount(1,
            $outbox->events
        );

        $event = $outbox->events[0];

        self::assertInstanceOf(
            BookingCreated::class,
            $event,
        );
        self::assertSame(
            'BookingCreated',
            $event->eventType(),
        );

        self::assertSame(
            'Booking',
            $event->aggregateType(),
        );

        self::assertSame(
            $id,
            $event->aggregateId(),
        );

        self::assertSame(
            [
                'bookingId' => $id,
                'slotId' => 1,
                'customerId' => 100,
            ],
            $event->payload(),
        );

        self::assertEquals(
            $clock->now(),
            $event->occurredAt(),
        );
    }
}

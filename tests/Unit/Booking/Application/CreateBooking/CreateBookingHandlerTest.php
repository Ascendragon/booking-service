<?php

declare(strict_types=1);

namespace App\Tests\Unit\Booking\Application\CreateBooking;

use App\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Booking\Domain\Booking;
use App\Booking\Domain\Exception\SlotAlreadyBooked;
use App\Tests\Fake\Booking\FakeBookingRepository;
use App\Tests\Fake\Shared\FakeClock;
use PHPUnit\Framework\TestCase;

final class CreateBookingHandlerTest extends TestCase
{

    public function testCreateBookingForAvailableSlot() : void
    {
        $repository = new FakeBookingRepository();
        $clock = new FakeClock();


        $handler = new CreateBookingHandler($repository, $clock);

        $command = new CreateBookingCommand(slotId: 1, customerId: 100);

        $handler($command);

        self::assertTrue(
            $repository->existsForSlot(1)
        );
    }

    public function testCreateBookingForUnavailableSlot(): void
    {
        $repository = new FakeBookingRepository();
        $clock = new FakeClock();

        $existingBooking = new Booking(1, 1, 100, $clock->now());
        $repository->save($existingBooking);

        $handler = new CreateBookingHandler(
            $repository,
            $clock
        );

        $command = new CreateBookingCommand(
            slotId: 1,
            customerId: 200
        );

        $this->expectException(SlotAlreadyBooked::class);

        $handler($command);
    }
}

<?php

namespace App\Booking\Application\CreateBooking;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingRepository;
use App\Booking\Domain\Exception\SlotAlreadyBooked;
use App\Shared\Clock;

final class CreateBookingHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private Clock $clock
    ) {}

    public function __invoke(CreateBookingCommand $command): int
    {
        if ($this->bookingRepository->existsForSlot($command->slotId)) {
            throw new SlotAlreadyBooked();
        }
        $booking = Booking::create($command->slotId, $command->customerId, $this->clock->now());

        return $this->bookingRepository->save($booking);
    }
}

<?php

namespace App\Booking\Application\CreateBooking;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingRepository;
use App\Booking\Domain\Event\BookingCreated;
use App\Booking\Domain\Exception\SlotAlreadyBooked;
use App\Outbox\Domain\OutboxRepository;
use App\Shared\Application\TransactionManager;
use App\Shared\Clock;

final class CreateBookingHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private Clock $clock,
        private TransactionManager $transactionManager,
        private OutboxRepository $outboxRepository
    ) {}

    public function __invoke(CreateBookingCommand $command): int
    {
        return $this->transactionManager->runInTransaction(
            function () use ($command) {

                if ($this->bookingRepository->existsForSlot($command->slotId)) {
                    throw new SlotAlreadyBooked();
                }
                $occurredAt = $this->clock->now();
                $booking = Booking::create(
                    $command->slotId,
                    $command->customerId,
                    $occurredAt
                );

                $bookingId = $this->bookingRepository->save($booking);

                $event = new BookingCreated(
                    bookingId: $bookingId,
                    slotId: $command->slotId,
                    customerId: $command->customerId,
                    occurredAt: $occurredAt,
                );
                $this->outboxRepository->save(
                    $event
                );

                return $bookingId;
            }
        );
    }
}

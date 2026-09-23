<?php

namespace App\Booking\Application\CreateBooking;

use App\Booking\Domain\Booking;
use App\Booking\Domain\BookingRepository;
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

                $booking = Booking::create(
                    $command->slotId,
                    $command->customerId,
                    $this->clock->now()
                );

                $bookingId = $this->bookingRepository->save($booking);

                $this->outboxRepository->save(
                    'BookingCreated',
                    'Booking',
                    $bookingId,
                    [
                        'bookingId' => $bookingId,
                        'slotId' => $command->slotId,
                        'customerId' => $command->customerId,
                    ]
                );

                return $bookingId;
            }
        );
    }
}

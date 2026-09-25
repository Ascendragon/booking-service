<?php

declare(strict_types=1);

namespace App\Tests\Fake\Outbox;

use App\Outbox\Application\MessagePublisher;
use App\Outbox\Application\OutboxMessage;
use RuntimeException;

final class FakeMessagePublisher implements MessagePublisher
{
    /** @var list<OutboxMessage> */
    public array $published = [];
    public ?int $failOnMessageId = null;

    public function publish(OutboxMessage $message): void
    {
        if ($message->id === $this->failOnMessageId) {
            throw new RuntimeException('Broker unavailable.');
        }

        $this->published[] = $message;
    }
}

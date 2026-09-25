<?php

declare(strict_types=1);

namespace App\Outbox\Application;

interface MessagePublisher
{
    public function publish(OutboxMessage $message): void;
}

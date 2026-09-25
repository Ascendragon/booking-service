<?php

declare(strict_types=1);

namespace App\Outbox\Application\Exception;

use RuntimeException;

final class OutboxOwnershipLost extends RuntimeException
{
    public static function forMessage(int $messageId): self
    {
        return new self(
            sprintf(
                'Ownership lost for outbox message %d.',
                $messageId,
            )
        );
    }
}

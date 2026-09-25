<?php

namespace App\Tests\Fake\Shared;

use App\Shared\Clock;
use DateTimeImmutable;

final class FakeClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2030-01-01 10:00:00');
    }
}

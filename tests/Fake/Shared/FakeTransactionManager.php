<?php

declare(strict_types=1);

namespace App\Tests\Fake\Shared;

use App\Shared\Application\TransactionManager;

final class FakeTransactionManager implements TransactionManager
{

    public function runInTransaction(callable $callback): mixed
    {
        return $callback();
    }
}

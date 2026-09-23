<?php

namespace App\Shared\Application;

interface TransactionManager
{
    public function runInTransaction(
        callable $callback
    ): mixed;
}

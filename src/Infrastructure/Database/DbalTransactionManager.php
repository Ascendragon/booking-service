<?php

namespace App\Infrastructure\Database;

use App\Shared\Application\TransactionManager;
use Doctrine\DBAL\Connection;

final class DbalTransactionManager implements TransactionManager
{
    public function __construct(private Connection $connection)
    {}

    public function runInTransaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $callback();

            $this->connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }
}

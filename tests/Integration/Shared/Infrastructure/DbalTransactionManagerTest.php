<?php

namespace App\Tests\Integration\Shared\Infrastructure;



use App\Shared\Application\TransactionManager;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DbalTransactionManagerTest extends KernelTestCase
{
    private Connection $connection;
    private TransactionManager $transactionManager;
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);


        $this->connection->delete('employees');

        $this->transactionManager = self::getContainer()->get(TransactionManager::class);


    }
    public function testCommitsTransaction(): void
    {
        $this->transactionManager->runInTransaction(
            function (): void {
                $this->connection->insert('employees', [
                    'name' => 'John',
                    'position' => 'Barber',
                ]);
            }
        );

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM employees'
        );

        self::assertSame(1, $count);
    }

    public function testRollbacksTransactionOnException(): void
    {
        try {
            $this->transactionManager->runInTransaction(
                function (): void {
                    $this->connection->insert('employees', [
                        'name' => 'John',
                        'position' => 'Barber',
                    ]);

                    throw new \RuntimeException();
                }
            );
        } catch (\RuntimeException) {
        }


        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM employees'
        );

        self::assertSame(0, $count);
    }
}

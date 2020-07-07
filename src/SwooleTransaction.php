<?php
declare(strict_types=1);
namespace Rabbit\DB\Mysql;

use DI\DependencyException;
use DI\NotFoundException;
use Psr\SimpleCache\InvalidArgumentException;
use Rabbit\Base\App;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\DB\Exception;
use Rabbit\DB\Transaction;
use Throwable;

/**
 * Class SwooleTransaction
 * @package Rabbit\DB\Mysql
 */
class SwooleTransaction extends Transaction
{
    /**
     * @param string|null $isolationLevel
     * @throws DependencyException
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     * @throws Exception
     * @throws Throwable
     */
    public function begin(?string $isolationLevel = null): void
    {
        if ($this->db === null) {
            throw new \InvalidArgumentException('Transaction::db must be set.');
        }
        $this->db->open();

        if ($this->_level === 0) {
            if ($isolationLevel !== null) {
                $this->db->getSchema()->setTransactionIsolationLevel($isolationLevel);
            }
            App::debug('Begin transaction' . ($isolationLevel ? ' with isolation level ' . $isolationLevel : ''), "db");
            $this->db->getConn()->begin();
            $this->_level = 1;
            return;
        }

        $schema = $this->db->getSchema();
        if ($schema->supportsSavepoint()) {
            App::debug('Set savepoint ' . $this->_level, "db");
            $schema->createSavepoint('LEVEL' . $this->_level);
        } else {
            App::info('Transaction not started: nested transaction not supported', "db");
            throw new NotSupportedException('Transaction not started: nested transaction not supported.');
        }
        $this->_level++;
    }
}

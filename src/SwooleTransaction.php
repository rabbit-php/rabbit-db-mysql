<?php

namespace rabbit\db\mysql;

use rabbit\App;
use rabbit\db\Transaction;
use rabbit\exception\NotSupportedException;

/**
 * Class SwooleTransaction
 * @package rabbit\db\mysql
 */
class SwooleTransaction extends Transaction
{
    /**
     * @param null $isolationLevel
     * @throws NotSupportedException
     * @throws \rabbit\db\Exception
     */
    public function begin($isolationLevel = null)
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

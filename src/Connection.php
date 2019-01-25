<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/23
 * Time: 16:16
 */

namespace rabbit\db\mysql;


use rabbit\db\ConnectionTrait;
use rabbit\exception\NotSupportedException;
use rabbit\pool\ConnectionInterface;

class Connection extends \rabbit\db\Connection implements ConnectionInterface
{
    use ConnectionTrait;

    public function createConnection(): void
    {
        $this->open();
    }

    public function reconnect(): void
    {
        $this->close();
        $this->open();
    }

    /**
     * @return bool
     */
    public function check(): bool
    {
        return $this->getIsActive();
    }

    /**
     * @param float $timeout
     * @return mixed|void
     * @throws NotSupportedException
     */
    public function receive(float $timeout = -1)
    {
        throw new NotSupportedException('can not call ' . __METHOD__);
    }

    /**
     * @param bool $release
     */
    public function release($release = false): void
    {
        $transaction = $this->getTransaction();
        if (!empty($transaction) && $transaction->getIsActive()) {//事务里面不释放连接
            return;
        }
        if ($this->isAutoRelease() || $release) {
            $this->pool->release($this);
        }
    }
}
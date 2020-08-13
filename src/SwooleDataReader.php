<?php
declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\DB\Command;
use Rabbit\DB\DataReader;
use ReflectionException;

/**
 * Class SwooleDataReader
 * @package Rabbit\DB\Mysql
 */
class SwooleDataReader extends DataReader
{
    /**
     * DataReader constructor.
     * @param Command $command
     * @param array $config
     * @throws ReflectionException
     */
    public function __construct(Command $command, $config = [])
    {
        $this->statement = $command->pdoStatement;
        configure($this, $config);
    }

    /**
     * @param int|string $column
     * @param mixed $value
     * @param int|null $dataType
     * @throws NotSupportedException
     */
    public function bindColumn($column, &$value, int $dataType = null): void
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @param int $mode
     * @throws NotSupportedException
     */
    public function setFetchMode(int $mode): void
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @param int $columnIndex
     * @return mixed|void
     * @throws NotSupportedException
     */
    public function readColumn(int $columnIndex)
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @param string $className
     * @param array $fields
     * @return mixed|void
     * @throws NotSupportedException
     */
    public function readObject(string $className, array $fields)
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @return bool
     */
    public function nextResult()
    {
        if (($result = $this->statement->nextResult()) !== false) {
            $this->index = -1;
        }

        return $result;
    }

    public function close(): void
    {
        while ($this->statement->fetch()) ;
        $this->closed = true;
    }

    public function getIsClosed(): bool
    {
        return $this->closed;
    }

    /**
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->statement->affected_rows;
    }

    /**
     * @return int|void
     * @throws NotSupportedException
     */
    public function getColumnCount(): int
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }
}

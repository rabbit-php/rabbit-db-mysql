<?php


namespace rabbit\db\mysql;


use rabbit\core\ObjectFactory;
use rabbit\db\Command;
use rabbit\db\DataReader;
use rabbit\exception\NotSupportedException;

class SwooleDataReader extends DataReader
{
    /**
     * DataReader constructor.
     * @param Command $command
     * @param array $config
     */
    public function __construct(Command $command, $config = [])
    {
        $this->_statement = $command->pdoStatement;
        ObjectFactory::configure($this, $config);
    }

    /**
     * @param int|string $column
     * @param mixed $value
     * @param null $dataType
     * @throws NotSupportedException
     */
    public function bindColumn($column, &$value, $dataType = null)
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @param int $mode
     * @throws NotSupportedException
     */
    public function setFetchMode($mode)
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @param int $columnIndex
     * @return mixed|void
     * @throws NotSupportedException
     */
    public function readColumn($columnIndex)
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @param string $className
     * @param array $fields
     * @return mixed|void
     * @throws NotSupportedException
     */
    public function readObject($className, $fields)
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }

    /**
     * @return bool
     */
    public function nextResult()
    {
        if (($result = $this->_statement->nextResult()) !== false) {
            $this->_index = -1;
        }

        return $result;
    }

    public function close()
    {
        while ($this->_statement->fetch()) {
        }
        $this->_closed = true;
    }

    public function getIsClosed()
    {
        return $this->_closed;
    }

    /**
     * @return int
     */
    public function getRowCount()
    {
        return $this->_statement->affected_rows;
    }

    /**
     * @return int|void
     * @throws NotSupportedException
     */
    public function getColumnCount()
    {
        throw new NotSupportedException("Swoole mysql not support " . __METHOD__);
    }
}
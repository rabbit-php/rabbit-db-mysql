<?php

namespace rabbit\db\mysql;

use Psr\SimpleCache\CacheInterface;
use rabbit\db\Command;
use rabbit\db\DataReader;
use rabbit\db\Exception;
use rabbit\exception\NotSupportedException;

/**
 * Class SwooleCommand
 * @package rabbit\db\mysql
 */
class SwooleCommand extends Command
{
    /**
     * @param null $forRead
     * @return \PDO|void
     * @throws Exception
     * @throws NotSupportedException
     */
    public function prepare($forRead = null)
    {
        if ($this->pdoStatement) {
            return;
        }

        $sql = $this->getSql();

        if ($this->db->getTransaction()) {
            // master is in a transaction. use the same connection.
            $forRead = false;
        }

        if ($forRead || $forRead === null && $this->db->getSchema()->isReadQuery($sql)) {
            $pdo = $this->db->getSlavePdo();
        } else {
            $pdo = $this->db->getMasterPdo();
        }
        try {
            if (false === $this->pdoStatement = $pdo->prepare($sql)) {
                throw new Exception($pdo->error);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage() . " Failed to prepare SQL: $sql";
            $e = new Exception($message, $pdo->error, (int)$e->getCode(), $e);
            throw $e;
        }
    }

    /**
     * @param string $method
     * @param null $fetchMode
     * @return mixed|DataReader
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     */
    protected function queryInternal($method, $fetchMode = null)
    {
        $rawSql = $this->getRawSql();

        if ($method !== '') {
            $info = $this->db->getQueryCacheInfo($this->queryCacheDuration, $this->cache);
            if (is_array($info)) {
                /* @var $cache CacheInterface */
                $cache = $info[0];
                $cacheKey = array_filter([
                    __CLASS__,
                    $method,
                    $fetchMode,
                    $this->db->dsn,
                    $rawSql ?: $rawSql = $this->getRawSql(),
                ]);
                $result = unserialize($cache->get($cacheKey));
                if (is_array($result) && isset($result[0])) {
                    $this->logQuery($rawSql . '; [Query result served from cache]');
                    return $result[0];
                }
            }
        }

        $this->logQuery($rawSql);

        try {
            $this->internalExecute($rawSql);
            $result = [];
            switch ($method) {
                case 'fetchAll':
                    while ($ret = $this->pdoStatement->fetch()) {
                        if ($fetchMode === \PDO::FETCH_COLUMN) {
                            foreach ($ret as $item) {
                                $result[] = is_array($item) ? current($item) : $item;
                            }
                        } else {
                            $result[] = $ret;
                        }
                    }
                    break;
                case 'fetch':
                    $index = 0;
                    while ($ret = $this->pdoStatement->fetch()) {
                        if ($index === 0) {
                            $result = $ret;
                        }
                    }
                    break;
                case 'fetchColumn':
                    $index = 0;
                    while ($ret = $this->pdoStatement->fetch()) {
                        if ($index === 0) {
                            $result = $fetchMode === 0 ? current($ret) : $ret;
                        }
                        $index++;
                    }
                    break;
                default:
                    $result = new SwooleDataReader($this);
            }
        } catch (\Throwable $e) {
            throw $e;
        }

        if (isset($cache, $cacheKey, $info)) {
            $cache->set($cacheKey, serialize([$result]), $info[1]) && $this->logQuery('Saved query result in cache', 'db');
        }

        return ($result === [] || $result === null) ? false : $result;
    }

    /**
     * @param string|null $rawSql
     * @throws Exception
     * @throws NotSupportedException
     */
    protected function internalExecute($rawSql)
    {
        $attempt = 0;
        while (true) {
            try {
                $this->prepare(true);
                if (
                    ++$attempt === 1
                    && $this->_isolationLevel !== false
                    && $this->db->getTransaction() === null
                ) {
                    $this->db->transaction(function () use ($rawSql) {
                        $this->internalExecute($rawSql);
                    }, $this->_isolationLevel);
                } else {
                    $this->pdoStatement->execute($this->params);
                }
                $this->params = [];
                $this->_pendingParams = [];
                break;
            } catch (\Throwable $e) {
                $rawSql = $rawSql ?: $this->getRawSql();
                $e = $this->db->getSchema()->convertException($e, $rawSql);
                $this->pdoStatement = null;
                if (($retryHandler = $this->db->getRetryHandler()) === null || !$retryHandler->handle($e, $attempt)) {
                    throw $e;
                }
                $this->db->reconnect($attempt);
            }
        }
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function execute()
    {
        $sql = $this->getSql();
        $rawSql = $this->getRawSql();
        $this->logQuery($rawSql);
        if ($sql == '') {
            return 0;
        }

        try {
            $this->internalExecute($rawSql);

            $n = [];
            while ($ret = $this->pdoStatement->fetch()) {
                $n[] = $ret;
            }
            if (empty($n)) {
                $n = $this->pdoStatement->affected_rows;
            }
            $this->refreshTableSchema();
            return ($n === [] || $n === null) ? false : $n;
        } catch (Exception $e) {
            throw $e;
        }
    }
}

<?php
declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Co\MySQL;
use Rabbit\Base\Core\Context;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\DbContext;
use Rabbit\DB\Exception;

/**
 * Class SwooleConnection
 * @package Rabbit\DB\Mysql
 */
class SwooleConnection extends Connection
{
    /** @var string */
    protected string $commandClass = SwooleCommand::class;
    /** @var string */
    protected string $transactionClass = SwooleTransaction::class;

    /**
     * SwooleConnection constructor.
     * @param string $dsn
     * @param string $poolKey
     */
    public function __construct(string $dsn, string $poolKey)
    {
        parent::__construct($dsn, $poolKey);
        $this->driver = 'swoole';
    }

    /**
     * @return MySQL
     * @throws Exception
     */
    public function createPdoInstance()
    {
        $parsed = $this->parseDsn;
        isset($parsed['query']) ? parse_str($parsed['query'], $parsed['query']) : $parsed['query'] = [];
        [$driver, $host, $port, $this->username, $this->password, $query] = ArrayHelper::getValueByArray(
            $parsed,
            ['scheme', 'host', 'port', 'user', 'pass', 'query'],
            null,
            ['mysql', 'localhost', '3306', '', '', []]
        );
        $client = new MySQL();
        $pool = $this->getPool();
        $maxRetry = $pool->getPoolConfig()->getMaxRetry();
        $reconnectCount = 0;
        $database = ArrayHelper::remove($query, 'dbname');
        while (true) {
            if (!$client->connect(array_merge([
                'host' => $host,
                'user' => $this->username,
                'password' => $this->password,
                'port' => $port,
                'database' => $database,
                'timeout' => $pool->getTimeout(),
                'strict_type' => true,
                'fetch_mode' => true
            ], $query))) {
                $reconnectCount++;
                if ($maxRetry > 0 && $reconnectCount >= $maxRetry) {
                    $error = sprintf(
                        'Service connect fail error=%s host=%s port=%s',
                        socket_strerror($client->connect_errno),
                        $host,
                        $port
                    );
                    throw new Exception($error);
                }
                $sleep = $pool->getPoolConfig()->getMaxWait();
                \Co::sleep($sleep ? $sleep : 1);
            } else {
                break;
            }
        }
        return $client;
    }

    /**
     * @param $conn
     */
    public function setInsertId($conn = null): void
    {
        $conn = $conn ?? DbContext::get($this->poolName, $this->driver);
        if ($conn !== null) {
            $conn->insert_id > 0 && Context::set($this->poolName . '.id', $conn->insert_id);
        }
    }
}

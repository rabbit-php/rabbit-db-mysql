<?php


namespace rabbit\db\mysql;

use Co\MySQL;
use rabbit\App;
use rabbit\core\ObjectFactory;
use rabbit\db\Command;
use rabbit\db\Exception;
use rabbit\exception\NotSupportedException;
use rabbit\helper\ArrayHelper;
use rabbit\pool\PoolManager;

class SwooleConnection extends Connection
{
    /** @var string */
    protected $commandClass = SwooleCommand::class;

    /**
     * @return bool
     */
    public function getIsActive()
    {
        return $this->pdo !== null && $this->pdo->connected;
    }

    /**
     * @param null $isolationLevel
     * @return SwooleTransaction|\rabbit\db\Transaction|null
     * @throws Exception
     * @throws NotSupportedException
     */
    public function beginTransaction($isolationLevel = null)
    {
        $this->open();

        if (($transaction = $this->getTransaction()) === null) {
            $transaction = $this->_transaction = new SwooleTransaction($this);
        }
        $transaction->begin($isolationLevel);

        return $transaction;
    }

    /**
     * @param int $attempt
     * @throws Exception
     * @throws NotSupportedException
     */
    public function open(int $attempt = 0)
    {
        if ($this->getIsActive()) {
            return;
        }

        if (!empty($this->masters)) {
            $db = $this->getMaster();
            if ($db !== null) {
                $this->pdo = $db->pdo;
                return;
            }

            throw new \InvalidArgumentException('None of the master DB servers is available.');
        }

        if (empty($this->dsn)) {
            throw new \InvalidArgumentException('Connection::dsn cannot be empty.');
        }

        $token = 'Opening DB connection: ' . $this->shortDsn;
        App::info($token, "db");
        $this->pdo = $this->createPdoInstance();
    }

    /**
     * @return MySQL|\PDO
     * @throws Exception
     */
    protected function createPdoInstance()
    {
        $parsed = parse_url($this->dsn);
        isset($parsed['query']) ? parse_str($parsed['query'], $parsed['query']) : $parsed['query'] = [];
        [$driver, $host, $port, $this->username, $this->password, $query] = ArrayHelper::getValueByArray(
            $parsed,
            ['scheme', 'host', 'port', 'user', 'pass', 'query'],
            null,
            ['mysql', 'localhost', '3306', '', '', []]
        );
        $client = new MySQL();
        $pool = PoolManager::getPool($this->poolKey);
        $maxRetry = $pool->getPoolConfig()->getMaxReonnect();
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
                $sleep = $pool->getPoolConfig()->getMaxWaitTime();
                \Co::sleep($sleep ? $sleep : 1);
            } else {
                break;
            }
        }
        return $client;
    }
}

<?php
declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use PDO;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\ConnectionInterface;

/**
 * Class Connection
 * @package rabbit\db\mysql
 */
class Connection extends \Rabbit\DB\Connection implements ConnectionInterface
{
    /** @var array|string[] */
    public array $schemaMap = [
        'mysqli' => Schema::class, // MySQL
        'mysql' => Schema::class, // MySQL
    ];

    /**
     * Connection constructor.
     * @param string $dsn
     * @param string $poolKey
     */
    public function __construct(string $dsn, string $poolKey)
    {
        parent::__construct($dsn);
        $this->poolKey = $poolKey;
        $this->driver = 'mysql';
    }

    /**
     * @return PDO
     */
    public function createPdoInstance()
    {
        $pdoClass = $this->pdoClass;
        $parsed = $this->parseDsn;
        isset($parsed['query']) ? parse_str($parsed['query'], $parsed['query']) : $parsed['query'] = [];
        [$driver, $host, $port, $this->username, $this->password, $query] = ArrayHelper::getValueByArray(
            $parsed,
            ['scheme', 'host', 'port', 'user', 'pass', 'query'],
            ['mysql', '127.0.0.1', '3306', '', '', []]
        );
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = "$key=$value";
        }
        $timeout = $this->getPool()->getTimeout();
        $dsn = "$driver:host=$host;port=$port;" . implode(';', $parts);
        return new $pdoClass($dsn, $this->username, $this->password, array_merge([
            PDO::ATTR_TIMEOUT => (int)$timeout,
        ], $this->attributes ?? []));
    }
}

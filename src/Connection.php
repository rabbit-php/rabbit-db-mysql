<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/23
 * Time: 16:16
 */

namespace rabbit\db\mysql;

use PDO;
use rabbit\activerecord\ActiveRecord;
use rabbit\App;
use rabbit\contract\InitInterface;
use rabbit\db\ConnectionTrait;
use rabbit\db\DbContext;
use rabbit\db\Expression;
use rabbit\db\JsonExpression;
use rabbit\exception\NotSupportedException;
use rabbit\helper\ArrayHelper;
use rabbit\helper\JsonHelper;
use rabbit\pool\ConnectionInterface;
use rabbit\pool\PoolManager;
use rabbit\web\HttpException;

class Connection extends \rabbit\db\Connection implements ConnectionInterface, InitInterface
{
    use ConnectionTrait;

    /**
     * Connection constructor.
     * @param array|null $dsn
     */
    public function __construct(string $dsn, string $poolKey)
    {
        parent::__construct($dsn);
        $this->lastTime = time();
        $this->connectionId = uniqid();
        $this->poolKey = $poolKey;
    }

    public function init()
    {
        $this->createConnection();
    }

    public function createConnection(): void
    {
        $this->open();
    }

    /**
     * @return mixed|\PDO
     */
    protected function createPdoInstance()
    {
        $pdoClass = $this->pdoClass;
        if ($pdoClass === null) {
            $pdoClass = 'PDO';
        }

        $parsed = parse_url($this->dsn);
        isset($parsed['query']) ? parse_str($parsed['query'], $parsed['query']) : $parsed['query'] = [];
        [$driver, $host, $port, $this->username, $this->password, $query] = ArrayHelper::getValueByArray(
            $parsed,
            ['scheme', 'host', 'port', 'user', 'pass', 'query'],
            null,
            ['mysql', 'localhost', '3306', '', '', []]
        );
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = "$key=$value";
        }
        $dsn = "$driver:host=$host;port=$port;" . implode(';', $parts);
        $timeout = PoolManager::getPool($this->poolKey)->getTimeout();
        return new $pdoClass($dsn, $this->username, $this->password, array_merge([
            PDO::ATTR_TIMEOUT => $timeout,
        ], $this->attributes));
    }

    public function reconnect(int $attempt = 0): void
    {
        unset($this->pdo);
        $this->pdo = null;
        App::warning('Reconnect DB connection: ' . $this->shortDsn, 'db');
        $this->open($attempt);
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
    public function release($release = false, string $name = 'db'): void
    {
        $transaction = $this->getTransaction();
        if (!empty($transaction) && $transaction->getIsActive()) {//事务里面不释放连接
            return;
        }
        if ($this->isAutoRelease() || $release) {
            PoolManager::getPool($this->poolKey)->release($this);
            DbContext::delete($name);
        }
    }

    /**
     * @param ActiveRecord $model
     * @param array $array_columns
     * @return int
     * @throws HttpException
     * @throws \rabbit\exception\InvalidConfigException
     */
    public function saveSeveral(ActiveRecord $model, array $array_columns): int
    {
        $sql = '';
        $params = array();
        $i = 0;
        if (ArrayHelper::isAssociative($array_columns)) {
            $array_columns = [$array_columns];
        }
        $keys = $model::primaryKey();

        $schema = $model::getDb()->getSchema();
        $tableSchema = $schema->getTableSchema($model::tableName());
        $columnSchemas = $tableSchema !== null ? $tableSchema->columns : [];

        foreach ($array_columns as $item) {
            $table = clone $model;
            //关联模型
            if (isset($table->realation)) {
                foreach ($table->realation as $key => $val) {
                    if (isset($item[$key])) {
                        $child = $table->getRelation($key)->modelClass;
                        $child_model = new $child();
                        if ($item[$key]) {
                            if (!isset($item[$key][0])) {
                                $item[$key] = [$item[$key]];
                            }
                            foreach ($val as $c_attr => $p_attr) {
                                foreach ($item[$key] as $index => $params) {
                                    $item[$key][$index][$c_attr] = $table->{$p_attr};
                                }
                            }
                            if ($this->updateSeveral($child_model, $item[$key]) === false) {
                                return false;
                            }
                        }
                    }
                }
            }
            $names = array();
            $placeholders = array();
            $table->load($item, '');
            $table->isNewRecord = false;
            if (!$table->validate()) {
                throw new HttpException(implode(BREAKS, $table->getFirstErrors()));
            }
            if ($keys) {
                foreach ($keys as $key) {
                    if (isset($item[$key])) {
                        $table[$key] = $item[$key];
                    }
                }
            }
            foreach ($table->toArray() as $name => $value) {
                $names[] = $this->quoteColumnName($name);
                if (!$i) {
                    $updates[] = $this->quoteColumnName($name) . "=values(" . $this->quoteColumnName($name) . ")";
                }
                $value = isset($columnSchemas[$name]) ? $columnSchemas[$name]->dbTypecast($value) : $value;
                if ($value instanceof Expression) {
                    $placeholders[] = $value->expression;
                    foreach ($value->params as $n => $v) {
                        $params[$n] = $v;
                    }
                } elseif ($value instanceof JsonExpression) {
                    $placeholders[] = '?';
                    $params[] = JsonHelper::encode($value);
                } else {
                    $placeholders[] = '?';
                    $params[] = $value;
                }
            }
            if (!$i) {
                $sql = 'INSERT INTO ' . $this->quoteTableName($table::tableName())
                    . ' (' . implode(', ', $names) . ') VALUES ('
                    . implode(', ', $placeholders) . ')';
            } else {
                $sql .= ',(' . implode(', ', $placeholders) . ')';
            }
            $i++;
        }
        $sql .= " on duplicate key update " . implode(', ', $updates);
        $result = $table::getDb()->createCommand($sql, $params)->execute();
        if (is_array($result)) {
            return end($result);
        }
        return $result;
    }

    /**
     * @param ActiveRecord $table
     * @param array $array_columns
     * @return int
     * @throws \rabbit\db\Exception
     * @throws \rabbit\exception\InvalidConfigException
     */
    public function deleteSeveral(ActiveRecord $table, array $array_columns): int
    {
        $result = false;
        $keys = $table::primaryKey();
        $condition = [];
        if (ArrayHelper::isAssociative($array_columns)) {
            $array_columns = [$array_columns];
        }
        foreach ($array_columns as $item) {
            $table->load($item, '');
            $table->isNewRecord = false;
            if (isset($table->realation)) {
                foreach ($table->realation as $key => $val) {
                    if (isset($item[$key])) {
                        $child = $table->getRelation($key)->modelClass;
                        $child_model = new $child();
                        if ($item[$key]) {
                            if ($this->deleteSeveral($child_model, $item[$key]) === false) {
                                return false;
                            }
                        }
                    }
                }
            }
            if ($keys) {
                foreach ($keys as $key) {
                    if (isset($item[$key])) {
                        $condition[$key][] = $item[$key];
                    }
                }
            }
        }
        if ($condition) {
            $result = $table->deleteAll($condition);
        }
        return $result;
    }
}

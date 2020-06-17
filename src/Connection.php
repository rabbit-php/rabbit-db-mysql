<?php
declare(strict_types=1);

namespace rabbit\db\mysql;

use PDO;
use rabbit\activerecord\ActiveRecord;
use rabbit\App;
use rabbit\db\DbContext;
use rabbit\db\Expression;
use rabbit\db\JsonExpression;
use rabbit\exception\NotSupportedException;
use rabbit\helper\ArrayHelper;
use rabbit\helper\JsonHelper;
use rabbit\pool\ConnectionInterface;
use rabbit\web\HttpException;

/**
 * Class Connection
 * @package rabbit\db\mysql
 */
class Connection extends \rabbit\db\Connection implements ConnectionInterface
{
    /**
     * Connection constructor.
     * @param array|null $dsn
     */
    public function __construct(string $dsn, string $poolKey)
    {
        parent::__construct($dsn);
        $this->poolKey = $poolKey;
        $this->driver = 'mysql';
    }

    /**
     * @return mixed|\PDO
     */
    public function createPdoInstance()
    {
        $pdoClass = $this->pdoClass;
        if ($pdoClass === null) {
            $pdoClass = 'PDO';
        }

        $parsed = $this->parseDsn;
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
        $timeout = $this->getPool()->getTimeout();
        return new $pdoClass($dsn, $this->username, $this->password, array_merge([
            PDO::ATTR_TIMEOUT => $timeout,
        ], $this->attributes ?? []));
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
        if (empty($array_columns)) {
            return 0;
        }
        $sql = '';
        $params = [];
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
            $table->load($item, '');
            //关联模型
            foreach ($table->getRelations() as $child => $val) {
                $key = explode("\\", $child);
                $key = strtolower(end($key));
                if (isset($item[$key])) {
                    $child_model = new $child();
                    if (!isset($item[$key][0])) {
                        $item[$key] = [$item[$key]];
                    }
                    foreach ($val as $c_attr => $p_attr) {
                        foreach ($item[$key] as $index => &$param) {
                            $param[$c_attr] = $table->{$p_attr};
                        }
                    }
                    if ($this->saveSeveral($child_model, $item[$key]) === false) {
                        return false;
                    }
                }
            }
            $names = array();
            $placeholders = array();
            $table->isNewRecord = false;
            if (!$table->validate()) {
                throw new HttpException(implode(BREAKS, $table->getFirstErrors()));
            }
            $tableArray = $table->toArray();
            if ($keys) {
                foreach ($keys as $key) {
                    if (isset($item[$key]) && (!isset($item[$key]) || $tableArray[$key] === null)) {
                        $tableArray[$key] = $item[$key];
                    }
                }
            }
            foreach ($tableArray as $name => $value) {
                if (!$i) {
                    $names[] = $this->quoteColumnName($name);
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
        if (empty($array_columns)) {
            return 0;
        }
        $result = false;
        $keys = $table::primaryKey();
        $condition = [];
        if (ArrayHelper::isAssociative($array_columns)) {
            $array_columns = [$array_columns];
        }
        foreach ($array_columns as $item) {
            $table->load($item, '');
            $table->isNewRecord = false;
            foreach ($table->getRelations() as $child => $val) {
                $key = strtolower(end(explode("\\", $child)));
                if (isset($item[$key])) {
                    $child_model = new $child();
                    if ($this->deleteSeveral($child_model, $item[$key]) === false) {
                        return false;
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

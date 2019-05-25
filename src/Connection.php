<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/23
 * Time: 16:16
 */

namespace rabbit\db\mysql;


use rabbit\activerecord\ActiveRecord;
use rabbit\db\ConnectionTrait;
use rabbit\db\DbContext;
use rabbit\db\Expression;
use rabbit\db\Transaction;
use rabbit\exception\NotSupportedException;
use rabbit\helper\ArrayHelper;
use rabbit\pool\ConnectionInterface;
use rabbit\web\HttpException;

class Connection extends \rabbit\db\Connection implements ConnectionInterface
{
    use ConnectionTrait;

    /**
     * Connection constructor.
     * @param array|null $dsn
     */
    public function __construct()
    {
        $this->lastTime = time();
        $this->connectionId = uniqid();
    }

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
    public function release($release = false, string $name = 'db'): void
    {
        $transaction = $this->getTransaction();
        if (!empty($transaction) && $transaction->getIsActive()) {//事务里面不释放连接
            return;
        }
        if ($this->isAutoRelease() || $release) {
            $this->pool->release($this);
            DbContext::delete($name);
        }
    }

    /**
     * 批量更新
     * @param ActiveRecord $model
     * @param array $array_columns
     * @param Transaction|null $transaction
     * @return mixed
     * @throws HttpException
     * @throws \rabbit\exception\InvalidConfigException
     */
    public function saveSeveral(ActiveRecord $model, array $array_columns, Transaction $transaction = null): int
    {
        $sql = '';
        $params = array();
        $i = 0;
        if (ArrayHelper::isAssociative($array_columns)) {
            $array_columns = [$array_columns];
        }
        $keys = $model::primaryKey();
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
                throw new HttpException(implode(BREAKS, $table->getErrors()));
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
                if ($value instanceof Expression) {
                    $placeholders[] = $value->expression;
                    foreach ($value->params as $n => $v) {
                        $params[$n] = $v;
                    }
                } else {
                    $placeholders[] = ':' . $name . $i;
                    $params[':' . $name . $i] = $value;
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
        return $table::getDb()->createCommand($sql, $params)->execute();
    }

    /**
     * @param ActiveRecord $table
     * @param array $array_columns
     * @param Transaction|null $transaction
     * @return bool|int
     * @throws \rabbit\db\Exception
     * @throws \rabbit\exception\InvalidConfigException
     */
    public function deleteSeveral(ActiveRecord $table, array $array_columns, Transaction $transaction = null): int
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
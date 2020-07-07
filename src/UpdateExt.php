<?php
declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\DBHelper;
use Rabbit\DB\Exception;
use Rabbit\DB\Query;

/**
 * Class UpdateExt
 * @package rabbit\db\mysql
 */
class UpdateExt
{
    /**
     * @param $model
     * @param array $body
     * @param bool $useOrm
     * @param bool $batch
     * @return array
     * @throws Exception
     */
    public static function update($model, array $body, bool $useOrm = false, bool $batch = true): array
    {
        if (isset($body['condition']) && $body['condition']) {
            $result = $useOrm ? $model::getDb()->createCommandExt(['update', [$body['edit'], $body['condition']]])->execute() :
                $model->updateAll($body['edit'], DBHelper::Search((new Query()), $body['condition'])->where);
            if ($result === false) {
                throw new Exception('Failed to update the object for unknown reason.');
            }
        } else {
            if (!ArrayHelper::isIndexed($body)) {
                $body = [$body];
            }
            if (!$batch) {
                $result = [];
                $exists = self::findExists($model, $body);
                foreach ($body as $params) {
                    $res = self::updateSeveral(clone $model, $params, self::checkExist($model, $params, $exists));
                    $result[] = $res;
                }
            } else {
                $result = $model::getDb()->saveSeveral($model, $body);
            }
        }
        return is_array($result) ? $result : [$result];
    }

    /**
     * @param $model
     * @param array $body
     * @param array $condition
     * @return array
     */
    private static function findExists($model, array $body, array $condition = []): array
    {
        $keys = $model::primaryKey();
        if (ArrayHelper::isAssociative($body)) {
            $body = [$body];
        }
        foreach ($keys as $key) {
            foreach ($body as $item) {
                if (array_key_exists($key, $item)) {
                    $condition[$key][] = $item[$key];
                }
            }
        }
        if ($condition !== [] && count($keys) === count($condition)) {
            return $model::find()->where($condition)->asArray()->all();
        }
        return [];
    }

    /**
     * @param $model
     * @param array $body
     * @param array|null $exist
     * @return array
     * @throws Exception
     */
    public static function updateSeveral($model, array $body, ?array $exist): array
    {
        $model->setOldAttributes($exist);
        $model->load($body, '');
        if ($model->save() === false && !$model->hasErrors()) {
            throw new Exception('Failed to update the object for unknown reason.');
        } else {
            $result = self::saveRealation($model, $body);
        }
        return $result;
    }

    /**
     * @param $model
     * @param array $body
     * @return array
     * @throws Exception
     */
    public static function saveRealation($model, array $body): array
    {
        $result = [];
        //关联模型
        foreach ($model->getRelations() as $child => $val) {
            $key = explode("\\", $child);
            $key = strtolower(end($key));
            if (isset($body[$key])) {
                $child_model = new $child();
                if (isset($params['edit']) && $params['edit']) {
                    $result[$key] = self::update($child_model, $params);
                } else {
                    if (ArrayHelper::isAssociative($body[$key])) {
                        $params = [$body[$key]];
                    } else {
                        $params = $body[$key];
                    }
                    $keys = $child::primaryKey();
                    $exists = self::findExists($child_model, $params);
                    foreach ($params as $param) {
                        if ($val) {
                            $child_model = new $child();
                            $child_id = key($val);
                            foreach ($val as $c_attr => $p_attr) {
                                $param[$c_attr] = $model->{$p_attr};
                            }
                            $result[$key][] = self::updateSeveral(
                                $child_model,
                                $param,
                                self::checkExist(
                                    $child_model,
                                    $param,
                                    $exists,
                                    [$child_id => $model[$val[$child_id]]]
                                )
                            );
                        }
                    }
                }
            }
        }
        $res = $model->toArray();
        foreach ($result as $key => $val) {
            $res[$key] = $val;
        }
        return $res;
    }

    /**
     * @param $model
     * @param array $body
     * @param array $exists
     * @return array|null
     */
    private static function checkExist($model, array $body, array $exists): ?array
    {
        if (!$exists) {
            return null;
        }
        $keys = $model::primaryKey();
        $existCount = 0;
        foreach ($exists as $exist) {
            foreach ($keys as $key) {
                if (isset($body[$key]) && $body[$key] == $exist[$key]) {
                    $existCount++;
                }
                if ($existCount === count($keys)) {
                    return $exist;
                }
            }
        }
        return null;
    }
}

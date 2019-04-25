<?php

namespace rabbit\db\mysql;

use rabbit\activerecord\ActiveRecord;
use rabbit\db\Exception;
use rabbit\db\Transaction;
use rabbit\helper\ArrayHelper;

/**
 * Class CreateExt
 * @package rabbit\db\mysql
 */
class CreateExt
{
    /**
     * @param ActiveRecord $model
     * @param array $body
     * @param Transaction|null $transaction
     * @return array
     * @throws \rabbit\db\Exception
     */
    public static function create(ActiveRecord $model, array $body, bool $hasRealation = false, Transaction $transaction = null): array
    {
        $transaction = $transaction ? $transaction : $model->getDb()->beginTransaction();
        try {
            if (ArrayHelper::isIndexed($body)) {
                if ($hasRealation) {
                    $result = [];
                    foreach ($body as $params) {
                        $res = self::createSeveral(clone $model, $params, $transaction);
                        $result[] = $res;
                    }
                } else {
                    $result = $model::getDb()->insertSeveral($model, $body);
                }
            } else {
                $result = self::createSeveral($model, $body, $transaction);
            }

            if ($transaction->getIsActive()) {
                $transaction->commit();
            }
        } catch (Exception $ex) {
            $transaction->rollBack();
            throw $ex;
        }

        return is_array($result) ? $result : [$result];
    }

    /**
     * @param ActiveRecord $model
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws Exception
     */
    private static function createSeveral(ActiveRecord $model, array $body, Transaction $transaction): array
    {
        if ($model->save()) {
            $result = self::saveRealation($model, $body, $transaction);
        } elseif (!$model->hasErrors()) {
            $transaction->rollBack();
            throw new Exception('Failed to create the object for unknown reason.');
        }
        return $result;
    }

    /**
     * @param ActiveRecord $model
     * @param array $body
     * @param Transaction $transaction
     * @return array
     * @throws Exception
     */
    private static function saveRealation(ActiveRecord $model, array $body, Transaction $transaction): array
    {
        $result = [];
        //关联模型
        if (isset($model->realation)) {
            foreach ($model->realation as $key => $val) {
                if (isset($body[$key])) {
                    $child = $model->getRelation($key)->modelClass;
                    if ($body[$key]) {
                        if (ArrayHelper::isAssociative($body[$key])) {
                            $body[$key] = [$body[$key]];
                        }
                        foreach ($body[$key] as $params) {
                            if ($val) {
                                foreach ($val as $c_attr => $p_attr) {
                                    $params[$c_attr] = $model->{$p_attr};
                                }
                            }
                            $child_model = new $child();
                            $res = self::createSeveral($child_model, $params, $transaction);
                            $result[$key][] = $res;
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

}

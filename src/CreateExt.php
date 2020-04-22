<?php

namespace rabbit\db\mysql;

use rabbit\activerecord\ActiveRecord;
use rabbit\db\Exception;
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
     * @param bool $hasRealation
     * @return array
     * @throws Exception
     */
    public static function create(
        ActiveRecord $model,
        array $body,
        bool $batch = true
    ): array
    {
        if (!ArrayHelper::isIndexed($body)) {
            $body = [$body];
        }
        $pks = $model::primaryKey();
        if (!$batch || !isset($body[0][current($pks)])) {
            $result = [];
            foreach ($body as $params) {
                $res = self::createSeveral(clone $model, $params);
                $result[] = $res;
            }
        } else {
            $result = $model::getDb()->saveSeveral($model, $body);
        }
        return is_array($result) ? $result : [$result];
    }

    /**
     * @param ActiveRecord $model
     * @param array $body
     * @return array
     * @throws Exception
     */
    private static function createSeveral(ActiveRecord $model, array $body): array
    {
        $model->load($body, '');
        if ($model->save()) {
            $result = self::saveRealation($model, $body);
        } elseif (!$model->hasErrors()) {
            throw new Exception('Failed to create the object for unknown reason.');
        } else {
            throw new Exception(implode(BREAKS, $model->getFirstErrors()));
        }
        return $result;
    }

    /**
     * @param ActiveRecord $model
     * @param array $body
     * @return array
     * @throws Exception
     */
    private static function saveRealation(ActiveRecord $model, array $body): array
    {
        $result = [];
        //关联模型
        foreach ($model->getRelations() as $child => $val) {
            $key = explode("\\", $child);
            $key = strtolower(end($key));
            if (isset($body[$key])) {
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
                    $res = self::createSeveral($child_model, $params);
                    $result[$key][] = $res;
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

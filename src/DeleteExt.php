<?php

namespace rabbit\db\mysql;

use rabbit\activerecord\ActiveRecord;
use rabbit\db\DBHelper;
use rabbit\db\Exception;
use rabbit\helper\ArrayHelper;

/**
 * Class DeleteExt
 * @package rabbit\db\mysql
 */
class DeleteExt
{
    /**
     * @param ActiveRecord $model
     * @param array $body
     * @return int
     * @throws Exception
     */
    public static function delete(ActiveRecord $model, array $body): int
    {
        if (ArrayHelper::isIndexed($body)) {
            $result = $model::getDb()->deleteSeveral($model, $body);
        } else {
            $result = $model->deleteAll(DBHelper::Search((new Query()), $body)->where);
        }
        if ($result === false || $result === []) {
            throw new Exception('Failed to delete the object for unknown reason.');
        }
        return $result;
    }
}

<?php


namespace rabbit\db\mysql;

use rabbit\activerecord\ActiveRecord;
use rabbit\db\DBHelper;

/**
 * Class ViewExt
 * @package rabbit\db\mysql
 */
class ViewExt
{
    /**
     * @param ActiveRecord $model
     * @param array $filter
     * @return array
     */
    public static function view(ActiveRecord $model, array $filter): array
    {
        $model = DBHelper::Search($model::find(), $filter)->asArray()->one();
        return $model;
    }
}
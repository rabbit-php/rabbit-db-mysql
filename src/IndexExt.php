<?php

namespace rabbit\db\mysql;

use rabbit\activerecord\ActiveRecord;
use rabbit\db\DBHelper;

/**
 * Class IndexExt
 * @package rabbit\db\mysql
 */
class IndexExt
{
    /**
     * @param ActiveRecord $model
     * @param array|null $filter
     * @param int|null $page
     * @return array
     */
    public static function index(ActiveRecord $model, array $filter = null, int $page = null): array
    {
        if ($filter instanceof Query) {
            return DBHelper::SearchList($filter, [], $page);
        } else {
            return DBHelper::SearchList($model::find(), $filter, $page);
        }
    }
}

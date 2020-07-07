<?php
declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\DBHelper;
use Rabbit\DB\Exception;
use Rabbit\DB\Query;

/**
 * Class DeleteExt
 * @package rabbit\db\mysql
 */
class DeleteExt
{
    /**
     * @param $model
     * @param array $body
     * @param bool $useOrm
     * @return int
     * @throws Exception
     */
    public static function delete($model, array $body, bool $useOrm = false): int
    {
        if (ArrayHelper::isIndexed($body)) {
            $result = $model::getDb()->deleteSeveral($model, $body);
        } else {
            $result = $useOrm ? $model::getDb()->createCommandExt(['delete', [$model::tableName(), $body]])->execute() :
                $model->deleteAll(DBHelper::Search((new Query()), ['where' => $body])->where);
        }
        if ($result === false) {
            throw new Exception('Failed to delete the object for unknown reason.');
        }
        return $result;
    }
}

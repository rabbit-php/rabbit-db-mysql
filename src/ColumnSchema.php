<?php

declare(strict_types=1);
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Rabbit\DB\Mysql;

use Rabbit\DB\ExpressionInterface;
use Rabbit\DB\JsonExpression;
use Rabbit\DB\PdoValue;
use Rabbit\DB\Query;

class ColumnSchema extends \Rabbit\DB\ColumnSchema
{
    public function dbTypecast(ExpressionInterface|PdoValue|Query|string|bool|array|int|float|null $value): ExpressionInterface|PdoValue|Query|string|bool|array|int|float|null
    {
        if ($value === null) {
            if ($this->dbType === Schema::TYPE_JSON) {
                return new JsonExpression([], $this->type);
            }

            if (
                $this->dbType === Schema::TYPE_TEXT ||
                $this->dbType === Schema::TYPE_MEDIUMTEXT ||
                $this->dbType === Schema::TYPE_TINYTEXT ||
                $this->dbType === Schema::TYPE_LONGTEXT
            ) {
                return '';
            }

            return $this->defaultValue;
        }

        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if ($this->dbType === Schema::TYPE_JSON) {
            return new JsonExpression($value, $this->type);
        }

        if (($this->dbType === Schema::TYPE_TIMESTAMP
            || $this->dbType === Schema::TYPE_DATETIME
            || $this->dbType === Schema::TYPE_TIME
            || $this->dbType === Schema::TYPE_DATE) && is_numeric($value)) {
            return date('Y-m-d H:i:s', $value);
        }

        return $this->typecast($value);
    }

    /**
     * {@inheritdoc}
     */
    public function phpTypecast(ExpressionInterface|PdoValue|Query|string|bool|array|int|float|null $value): ExpressionInterface|PdoValue|Query|string|bool|array|int|float|null
    {
        if ($value === null) {
            return null;
        }

        if ($this->type === Schema::TYPE_JSON) {
            return json_decode($value, true);
        }

        return parent::phpTypecast($value);
    }
}

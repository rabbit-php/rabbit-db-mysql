<?php
declare(strict_types=1);
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Rabbit\DB\Mysql;

use Psr\SimpleCache\InvalidArgumentException;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\DB\ColumnSchemaBuilder as AbstractColumnSchemaBuilder;
use Throwable;

class ColumnSchemaBuilder extends AbstractColumnSchemaBuilder
{
    public function __toString()
    {
        switch ($this->getTypeCategory()) {
            case self::CATEGORY_PK:
                $format = '{type}{length}{check}{comment}{append}{pos}';
                break;
            case self::CATEGORY_NUMERIC:
                $format = '{type}{length}{unsigned}{notnull}{unique}{default}{check}{comment}{append}{pos}';
                break;
            default:
                $format = '{type}{length}{notnull}{unique}{default}{check}{comment}{append}{pos}';
        }

        return $this->buildCompleteString($format);
    }

    protected function buildUnsignedString(): string
    {
        return $this->isUnsigned ? ' UNSIGNED' : '';
    }

    protected function buildAfterString(): string
    {
        return $this->after !== null ?
            ' AFTER ' . $this->db->quoteColumnName($this->after) :
            '';
    }

    protected function buildFirstString(): string
    {
        return $this->isFirst ? ' FIRST' : '';
    }

    protected function buildCommentString(): string
    {
        return $this->comment !== null ? ' COMMENT ' . $this->db->quoteValue($this->comment) : '';
    }
}

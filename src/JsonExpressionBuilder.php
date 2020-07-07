<?php
declare(strict_types=1);
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Rabbit\DB\Mysql;

use Rabbit\Base\Helper\JsonHelper;
use Rabbit\DB\Exception;
use Rabbit\DB\ExpressionBuilderInterface;
use Rabbit\DB\ExpressionBuilderTrait;
use Rabbit\DB\ExpressionInterface;
use Rabbit\DB\JsonExpression;
use Rabbit\DB\Query;

/**
 * Class JsonExpressionBuilder builds [[JsonExpression]] for MySQL DBMS.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class JsonExpressionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;


    /**
     * {@inheritdoc}
     * @param JsonExpression|ExpressionInterface $expression the expression to be built
     * @throws Exception
     */
    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $value = $expression->getValue();

        if ($value instanceof Query) {
            list($sql, $params) = $this->queryBuilder->build($value, $params);
            return "($sql)";
        }

        $params[count($params)] = JsonHelper::encode($value);

        return "CAST(? AS JSON)";
    }
}

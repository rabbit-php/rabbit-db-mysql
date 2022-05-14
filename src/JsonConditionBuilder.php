<?php

declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Rabbit\DB\ExpressionBuilderInterface;
use Rabbit\DB\ExpressionBuilderTrait;
use Rabbit\DB\ExpressionInterface;

class JsonConditionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;

    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $operator = $expression->getOperator();
        $column = $expression->getColumn();
        switch (strtoupper($operator)) {
            case 'JSON_OVERLAPS':
                $value = json_encode($expression->getValue());
                break;
            default:
                $value = implode(',', $expression->getValue());
        }


        if ($value instanceof ExpressionInterface) {
            return "{$operator}($column,{$this->queryBuilder->buildExpression($value,$params)})";
        }

        $phName = $this->queryBuilder->bindParam($value, $params);
        return "{$operator}({$column},{$phName})";
    }
}

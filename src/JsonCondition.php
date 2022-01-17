<?php

declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Rabbit\DB\Conditions\ConditionInterface;

class JsonCondition implements ConditionInterface
{
    public function __construct(private string $column, private string $operator, private array $value)
    {
    }

    public static function fromArrayDefinition(string $operator, array $operands): self
    {
        if (count($operands) !== 2) {
            throw new \InvalidArgumentException("Operator '$operator' requires two operands.");
        }

        return new static($operands[0], $operator, $operands[1]);
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getValue(): array
    {
        return $this->value;
    }
}

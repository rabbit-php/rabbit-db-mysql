<?php

declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Rabbit\DB\Conditions\ConditionInterface;

class JsonCondition implements ConditionInterface
{
    private string $operator;
    private string $column;
    private array $value;

    public function __construct(string $column, string $operator, array $value)
    {
        $this->column = $column;
        $this->operator = $operator;
        $this->value = $value;
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

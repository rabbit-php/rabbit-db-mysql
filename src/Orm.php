<?php
declare(strict_types=1);

namespace rabbit\db\mysql;

use DI\DependencyException;
use DI\NotFoundException;
use rabbit\db\Connection;
use rabbit\db\Exception;

/**
 * Class Orm
 * @package rabbit\db\mysql
 */
class Orm
{
    /**
     * @param Connection $connection
     * @param string $table
     * @param array $body
     * @param string $method
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    public static function search(Connection $connection, string $table, array $body = [], string $method = 'queryAll')
    {
        return $connection->createCommandExt(['select', array_merge([$table], $body)])->$method();
    }

    /**
     * @param Connection $connection
     * @param string $table
     * @param array $body
     * @return int
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Exception
     */
    public static function create(Connection $connection, string $table, array $body = []): int
    {
        return $connection->createCommandExt(['create', array_merge([$table], $body)])->execute();
    }

    /**
     * @param Connection $connection
     * @param string $table
     * @param array $body
     * @param array $where
     * @return int
     * @throws DependencyException
     * @throws Exception
     * @throws NotFoundException
     */
    public static function update(Connection $connection, string $table, array $body = [], array $where = []): int
    {
        return $connection->createCommandExt(['update', array_merge([$table], $body, [$where])])->execute();
    }

    /**
     * @param Connection $connection
     * @param string $table
     * @param array $body
     * @return int
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Exception
     */
    public static function replace(Connection $connection, string $table, array $body = []): int
    {
        return $connection->createCommandExt(['replace', array_merge([$table], [$body])])->execute();
    }

    /**
     * @param Connection $connection
     * @param string $table
     * @param array $body
     * @return int
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Exception
     */
    public static function delete(Connection $connection, string $table, array $body = []): int
    {
        return $connection->createCommandExt(['delete', array_merge([$table], [$body])])->execute();
    }
}

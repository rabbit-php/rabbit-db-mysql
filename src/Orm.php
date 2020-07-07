<?php
declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use DI\DependencyException;
use DI\NotFoundException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\DB\Exception;
use Throwable;

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
     * @param int $duration
     * @param CacheInterface|null $cache
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public static function search(Connection $connection, string $table, array $body = [], string $method = 'queryAll', int $duration = -1, ?CacheInterface $cache = null)
    {
        return $connection->createCommandExt(['select', array_merge([$table], $body)])->cache($duration, $cache)->$method();
    }

    /**
     * @param Connection $connection
     * @param string $table
     * @param array $body
     * @return int
     * @throws DependencyException
     * @throws Exception
     * @throws NotFoundException
     * @throws Throwable
     * @throws InvalidArgumentException
     * @throws NotSupportedException
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
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws NotSupportedException
     * @throws Throwable
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
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws NotSupportedException
     * @throws Throwable
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
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws NotSupportedException
     * @throws Throwable
     */
    public static function delete(Connection $connection, string $table, array $body = []): int
    {
        return $connection->createCommandExt(['delete', array_merge([$table], [$body])])->execute();
    }
}

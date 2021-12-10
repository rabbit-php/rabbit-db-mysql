<?php

declare(strict_types=1);

namespace Rabbit\DB\Mysql;

use Throwable;
use Rabbit\DB\Exception;

/**
 * Class RetryHandler
 * @package Rabbit\DB\Mysql
 */
class RetryHandler extends \Rabbit\DB\RetryHandler
{
    protected array $retryCode = [1213];
    /**
     * @param Throwable $e
     * @param int $count
     * @return bool
     */
    public function handle(Throwable $e, int $count): int
    {
        if ($this->isConnectionError($e) && $count < $this->totalCount) {
            $count > 1 && sleep($this->sleep);
            return static::RETRY_CONNECT;
        }
        if ($this->isRetry($e) && $count < $this->totalCount) {
            return static::RETRY_NOCONNECT;
        }
        return static::RETRY_NO;
    }

    private function isRetry(Throwable $exception): bool
    {
        if ($exception instanceof Exception) {
            $errorInfo = $exception->errorInfo;
            if (($errorInfo[1] ?? false) &&  in_array((int)$errorInfo[1], $this->retryCode)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Throwable $exception
     * @return bool
     */
    private function isConnectionError(Throwable $exception): bool
    {
        if ($exception instanceof Exception) {
            $errorInfo = $exception->errorInfo;
            if (($errorInfo[1] ?? false) && ((int)$errorInfo[0] === 70100 || (int)$errorInfo[0] === 2006)) {
                return true;
            }
        }
        return false;
    }
}

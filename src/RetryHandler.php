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
    /**
     * @param Throwable $e
     * @param int $count
     * @return bool
     */
    public function handle(Throwable $e, int $count): bool
    {
        $isConnectionError = $this->isConnectionError($e);
        if ($isConnectionError && $count < $this->totalCount) {
            $count > 1 && sleep($this->sleep);
            return true;
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
            if (!empty($errorInfo) && ($errorInfo[1] == 70100 || $errorInfo[1] == 2006)) {
                return true;
            } elseif (strpos($exception->getMessage(), 'MySQL server has gone away') !== false || strpos(
                $exception->getMessage(),
                'Error while sending QUERY packet. PID='
            ) !== false) {
                return true;
            }
        }
        return false;
    }
}

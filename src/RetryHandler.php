<?php


namespace rabbit\db\mysql;

use rabbit\db\Command;
use rabbit\db\Exception;
use rabbit\db\RetryHandlerInterface;

/**
 * Class RetryHandler
 * @package rabbit\db\mysql
 */
class RetryHandler implements RetryHandlerInterface
{
    /** @var int */
    private $totalCount;

    /**
     * RetryHandler constructor.
     * @param int $totalCount
     */
    public function __construct(int $totalCount = 3)
    {
        $this->totalCount = $totalCount;
    }

    /**
     * @param int $count
     */
    public function setTotalCount(int $count): void
    {
        $this->totalCount = $count;
    }

    /**
     * @param Connection $db
     * @param \Throwable $e
     * @param int $count
     */
    public function handle(Command $cmd, \Throwable $e, int $count): bool
    {
        if ($count >= $this->totalCount) {
            $this->totalCount = 0;
            return false;
        }
        $isConnectionError = $this->isConnectionError($e);
        if ($isConnectionError) {
            $cmd->cancel();
            $cmd->db->reconnect();
            return true;
        }
        return false;
    }

    /**
     * @param Command $cmd
     * @param \Throwable $exception
     * @return bool
     */
    private function isConnectionError(\Throwable $exception): bool
    {
        if ($exception instanceof Exception) {
            $errorInfo = $exception->errorInfo;
            if ($errorInfo[1] == 70100 || $errorInfo[1] == 2006) {
                return true;
            }
        } elseif (strpos($exception->getMessage(), 'MySQL server has gone away') !== false || strpos($message,
                'Error while sending QUERY packet. PID=') !== false) {
            return true;
        }
        return false;
    }
}
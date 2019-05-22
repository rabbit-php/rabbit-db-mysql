<?php


namespace rabbit\db\mysql;

use rabbit\db\Command;

/**
 * Class RetryHandler
 * @package rabbit\db\mysql
 */
class RetryHandler
{
    /** @var int */
    private $totalCount = 3;

    /**
     * @param Connection $db
     * @param \Throwable $e
     * @param int $count
     */
    public function handle(Command $cmd, \Throwable $e, int $count): bool
    {
        if ($count >= $this->totalCount) {
            return false;
        }
        $isConnectionError = $this->isConnectionError($e);
        if ($isConnectionError) {
            $cmd->cancel();
            $cmd->db->reconnect();
            $this->totalCount++;
        }
    }

    /**
     * @param \Throwable $exception
     * @return bool
     */
    private function isConnectionError(\Throwable $exception): bool
    {
        if ($exception instanceof \PDOException) {
            $errorInfo = $this->pdoStatement->errorInfo();
            if ($errorInfo[1] == 70100 || $errorInfo[1] == 2006) {
                return true;
            }
        } elseif ($exception instanceof \ErrorException) {
            if (strpos($exception->getMessage(), 'MySQL server has gone away') !== false) {
                return true;
            }
        }
        $message = $exception->getMessage();
        if (strpos($message, 'Error while sending QUERY packet. PID=') !== false) {
            return true;
        }
        return false;
    }
}
<?php

declare(strict_types=1);

namespace Tests\Utils;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use ReflectionClass;

trait Logger
{
    protected ?LoggerInterface $logger = null;

    public function getLogger(): LoggerInterface
    {
        if (!$this->logger) {
            $reflection = new ReflectionClass(get_class($this));
            $name = $reflection->getShortName();
            foreach (debug_backtrace() as $trace) {
                if ($trace['class'] == __CLASS__) {
                    continue;
                }
                $name .= '.' . $trace['function'];
                break;
            }
            $this->logger = new MonologLogger($name);
            $this->logger->pushHandler(new StreamHandler('php://stdout', MonologLogger::DEBUG));
        }
        return $this->logger;
    }
}

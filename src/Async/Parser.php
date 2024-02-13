<?php

declare(strict_types=1);

namespace Basis\Nats\Async;

use Amp\Pipeline\Queue;

class Parser extends \Amp\Parser\Parser
{
    private const CRLF = "\r\n";

    public function __construct(Queue $queue)
    {
        parent::__construct(self::parser($queue));
    }

    private static function parser(Queue $queue): \Generator
    {
        while(true) {
            try {
                $line = yield self::CRLF;

                if(str_starts_with($line, 'MSG')) {
                    $payload = yield self::CRLF;
                    $queue->push([$line, $payload]);
                    continue;
                }

                $queue->push($line);
            } catch(\Throwable $exception) {
                // todo: handle exception?
                throw $exception;
            }
        }
    }
}

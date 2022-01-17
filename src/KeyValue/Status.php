<?php

declare(strict_types=1);

namespace Basis\Nats\KeyValue;

class Status
{
    public int $values;
    public int $history;
    public int $ttl;

    public function __construct(public readonly string $bucket, $streamInfo)
    {
        $this->values = $streamInfo->state->messages;
        $this->history = $streamInfo->config->max_msgs_per_subject;
        $this->ttl = $streamInfo->config->max_age;
    }
}

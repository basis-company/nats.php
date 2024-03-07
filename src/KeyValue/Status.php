<?php

declare(strict_types=1);

namespace Basis\Nats\KeyValue;

class Status
{
    public $values;
    public $history;
    public $ttl;
    public $bucket;

    public function __construct(string $bucket, $streamInfo)
    {
        $this->values = $streamInfo->state->messages;
        $this->history = $streamInfo->config->max_msgs_per_subject;
        $this->ttl = $streamInfo->config->max_age;
        $this->bucket = $bucket;
    }
}

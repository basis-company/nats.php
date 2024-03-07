<?php

declare(strict_types=1);

namespace Basis\Nats\KeyValue;

class Entry
{
    public $bucket;
    public $key;
    public $value;
    public $revision;

    public function __construct(
        string $bucket,
        string $key,
        $value,
        int $revision
    ) {
        $this->bucket = $bucket;
        $this->key = $key;
        $this->value = $value;
        $this->revision = $revision;
    }
}

<?php

declare(strict_types=1);

namespace Basis\Nats\KeyValue;

use Basis\Nats\Stream\Configuration as StreamConfiguration;
use Basis\Nats\Stream\DiscardPolicy;

class Configuration
{
    private ?int $history = null;
    private ?int $maxBytes = null;
    private ?int $maxValueSize = null;
    private ?int $replicas = null;
    private ?int $ttl = null;

    public function __construct(private readonly string $name)
    {
    }

    public function configureStream(StreamConfiguration $configuration): self
    {
        $configuration
            ->setAllowRollupHeaders(true)
            ->setDiscardPolicy(DiscardPolicy::NEW)
            ->setDenyDelete(false)
            ->setMaxAge($this->getTtl() ?? 0)
            ->setMaxBytes($this->getMaxBytes())
            ->setMaxMessageSize($this->getMaxValueSize())
            ->setMaxMessagesPerSubject($this->getHistory() ?? 1)
            ->setReplicas($this->getReplicas() ?? 1)
            ->setSubjects(["\$KV.$this->name.*"]);

        return $this;
    }

    public function getHistory(): ?int
    {
        return $this->history;
    }

    public function getMaxBytes(): ?int
    {
        return $this->maxBytes;
    }

    public function getMaxValueSize(): ?int
    {
        return $this->maxValueSize;
    }

    public function getReplicas(): ?int
    {
        return $this->replicas;
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function setHistory(?int $history): self
    {
        $this->history = $history;
        return $this;
    }

    public function setMaxBytes(?int $maxBytes): self
    {
        $this->maxBytes = $maxBytes;
        return $this;
    }

    public function setMaxValueSize(?int $maxValueSize): self
    {
        $this->maxValueSize = $maxValueSize;
        return $this;
    }

    public function setReplicas(?int $replicas): self
    {
        $this->replicas = $replicas;
        return $this;
    }

    public function setTtl(?int $ttl): self
    {
        $this->ttl = $ttl;
        return $this;
    }
}

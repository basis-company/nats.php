<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use Closure;
use Basis\Nats\Client;

class Configuration
{
    private ?string $subjectFilter = null;
    private string $ackPolicy = AckPolicy::EXPLICIT;

    public function __construct(
        private readonly string $stream,
        private readonly string $name
    ) {
    }

    public function getAckPolicy(): string
    {
        return $this->ackPolicy;
    }

    public function getName(): string
    {
        return strtoupper($this->name);
    }

    public function getStream(): string
    {
        return $this->stream;
    }

    public function getSubjectFilter(): ?string
    {
        return $this->subjectFilter;
    }

    public function setAckPolicy(string $ackPolicy): self
    {
        $this->ackPolicy = AckPolicy::validate($ackPolicy);
        return $this;
    }

    public function setSubjectFilter(string $subjectFilter): self
    {
        $this->subjectFilter = $subjectFilter;
        return $this;
    }

    public function toArray(): array
    {
        $array = [
            'stream_name' => $this->getStream(),
            'config' => [
                'ack_policy' => $this->getAckPolicy(),
                'durable_name' => $this->getName(),
            ],
        ];

        if ($this->getSubjectFilter()) {
            $array['config']['filter_subject'] = $this->getSubjectFilter();
        }

        return $array;
    }
}

<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use Closure;
use Basis\Nats\Client;

class Configuration
{
    private ?string $ackWait = null;
    private ?string $deliverGroup = null;
    private ?string $deliverPolicy = null;
    private ?string $deliverSubject = null;
    private ?string $description = null;
    private ?string $flowControl = null;
    private ?string $gilterSubject = null;
    private ?string $headersOnly = null;
    private ?string $idleHeartbeat = null;
    private ?int $maxAckPending = null;
    private ?string $maxDeliver = null;
    private ?string $maxWaiting = null;
    private ?string $optStartSeq = null;
    private ?string $optStartTime = null;
    private ?string $replayPolicy = null;
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
        return $this->name;
    }

    public function getStream(): string
    {
        return $this->stream;
    }

    public function getSubjectFilter(): ?string
    {
        return $this->subjectFilter;
    }

    public function getAckWait()
    {
        return $this->ackWait;
    }

    public function getDeliverGroup()
    {
        return $this->deliverGroup;
    }

    public function getDeliverPolicy()
    {
        return $this->deliverPolicy;
    }

    public function getDeliverSubject()
    {
        return $this->deliverSubject;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getGilterSubject()
    {
        return $this->gilterSubject;
    }

    public function getFlowControl()
    {
        return $this->flowControl;
    }

    public function getHeadersOnly()
    {
        return $this->headersOnly;
    }

    public function getIdleHeartbeat()
    {
        return $this->idleHeartbeat;
    }

    public function getMaxAckPending()
    {
        return $this->maxAckPending;
    }

    public function getMaxDeliver()
    {
        return $this->maxDeliver;
    }

    public function getMaxWaiting()
    {
        return $this->maxWaiting;
    }

    public function getOptStartSeq()
    {
        return $this->optStartSeq;
    }

    public function getOptStartTime()
    {
        return $this->optStartTime;
    }

    public function getReplayPolicy()
    {
        return $this->replayPolicy;
    }

    public function setAckPolicy(string $ackPolicy): self
    {
        $this->ackPolicy = AckPolicy::validate($ackPolicy);
        return $this;
    }

    public function setMaxAckPending(int $maxAckPending): self
    {
        $this->maxAckPending = $maxAckPending;
        return $this;
    }

    public function setSubjectFilter(string $subjectFilter): self
    {
        $this->subjectFilter = $subjectFilter;
        return $this;
    }

    public function toArray(): array
    {
        $config = [
            'ack_policy' => $this->getAckPolicy(),
            'ack_wait' => $this->getAckWait(),
            'deliver_group' => $this->getDeliverGroup(),
            'deliver_policy' => $this->getDeliverPolicy(),
            'deliver_subject' => $this->getDeliverSubject(),
            'description' => $this->getDescription(),
            'durable_name' => $this->getName(),
            'filter_subject' => $this->getGilterSubject(),
            'flow_control' => $this->getFlowControl(),
            'headers_only' => $this->getHeadersOnly(),
            'idle_heartbeat' => $this->getIdleHeartbeat(),
            'max_ack_pending' => $this->getMaxAckPending(),
            'max_deliver' => $this->getMaxDeliver(),
            'max_waiting' => $this->getMaxWaiting(),
            'opt_start_seq' => $this->getOptStartSeq(),
            'opt_start_time' => $this->getOptStartTime(),
            'replay_policy' => $this->getReplayPolicy(),
        ];

        if ($this->getSubjectFilter()) {
            $config['filter_subject'] = $this->getSubjectFilter();
        }
        foreach ($config as $k => $v) {
            if ($v === null) {
                unset($config[$k]);
            }
        }

        return [
            'stream_name' => $this->getStream(),
            'config' => $config,
        ];
    }
}

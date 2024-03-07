<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use DateTimeInterface;

class Configuration
{
    private const OPT_START_TIME_FORMAT = 'Y-m-d\TH:i:s.uP'; # ISO8601 with microseconds

    private $ephemeral = false;
    private $flowControl = null;
    private $headersOnly = null;
    private $ackWait = null;
    private $idleHeartbeat = null;
    private $maxAckPending = null;
    private $maxDeliver = null;
    private $maxWaiting = null;
    private $startSequence = null;
    private $startTime = null;
    private $deliverGroup = null;
    private $deliverSubject = null;
    private $description = null;
    private $subjectFilter = null;
    private $ackPolicy = AckPolicy::EXPLICIT;
    private $deliverPolicy = DeliverPolicy::ALL;
    private $replayPolicy = ReplayPolicy::INSTANT;
    private $stream;
    private $name;

    public function __construct(
        string $stream,
        ?string $name = null
    ) {
        $this->stream = $stream;
        $this->name = $name;
    }

    public function getAckPolicy(): string
    {
        return $this->ackPolicy;
    }

    public function getName(): ?string
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

    public function getStartSequence()
    {
        return $this->startSequence;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function getReplayPolicy()
    {
        return $this->replayPolicy;
    }

    public function isEphemeral(): bool
    {
        return $this->ephemeral;
    }

    public function ephemeral(): self
    {
        $this->ephemeral = true;
        return $this;
    }

    public function setAckPolicy(string $ackPolicy): self
    {
        $this->ackPolicy = AckPolicy::validate($ackPolicy);
        return $this;
    }

    public function setAckWait(int $ackWait): self
    {
        $this->ackWait = $ackWait;
        return $this;
    }

    public function setDeliverPolicy(string $deliverPolicy): self
    {
        $this->deliverPolicy = DeliverPolicy::validate($deliverPolicy);
        return $this;
    }

    public function setMaxAckPending(int $maxAckPending): self
    {
        $this->maxAckPending = $maxAckPending;
        return $this;
    }

    public function setMaxDeliver(int $maxDeliver): self
    {
        $this->maxDeliver = $maxDeliver;
        return $this;
    }

    public function setMaxWaiting(int $maxWaiting): self
    {
        $this->maxWaiting = $maxWaiting;
        return $this;
    }

    public function setName(string $name): self
    {
        if ($this->isEphemeral()) {
            $this->name = $name;
        }

        return $this;
    }

    public function setStartSequence(int $startSeq): self
    {
        $this->startSequence = $startSeq;
        return $this;
    }

    public function setStartTime(DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function setReplayPolicy(string $replayPolicy): self
    {
        $this->replayPolicy = ReplayPolicy::validate($replayPolicy);
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
            'durable_name' => $this->isEphemeral() ? null : $this->getName(),
            'flow_control' => $this->getFlowControl(),
            'headers_only' => $this->getHeadersOnly(),
            'idle_heartbeat' => $this->getIdleHeartbeat(),
            'max_ack_pending' => $this->getMaxAckPending(),
            'max_deliver' => $this->getMaxDeliver(),
            'max_waiting' => $this->getMaxWaiting(),
            'replay_policy' => $this->getReplayPolicy(),
        ];

        switch ($this->getDeliverPolicy()) {
            case DeliverPolicy::BY_START_SEQUENCE:
                $config['opt_start_seq'] = $this->getStartSequence();
                break;

            case DeliverPolicy::BY_START_TIME:
                $config['opt_start_time'] = null;
                if ($this->getStartTime() !== null) {
                    $config['opt_start_time'] = $this->getStartTime()->format(self::OPT_START_TIME_FORMAT);
                }
                break;
        }

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

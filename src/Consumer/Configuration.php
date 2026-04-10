<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use DateTimeInterface;

class Configuration
{
    private const OPT_START_TIME_FORMAT = 'Y-m-d\TH:i:s.uP'; # ISO8601 with microseconds

    private bool $ephemeral = false;
    private ?bool $flowControl = null;
    private ?bool $headersOnly = null;
    private ?int $ackWait = null;
    private ?int $idleHeartbeat = null;
    private ?int $maxAckPending = null;
    private ?int $maxDeliver = null;
    private ?int $maxWaiting = null;
    private ?int $startSequence = null;
    private ?DateTimeInterface $startTime = null;
    private ?string $deliverGroup = null;
    private ?string $deliverSubject = null;
    private ?string $description = null;
    private ?string $subjectFilter = null;
    private ?array $subjectFilters = null;
    private string $ackPolicy = AckPolicy::EXPLICIT;
    private string $deliverPolicy = DeliverPolicy::ALL;
    private string $replayPolicy = ReplayPolicy::INSTANT;
    private ?int $inactiveThreshold = null;

    public function __construct(
        private readonly string $stream,
        private ?string $name = null
    ) {
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

    public function getAckWait(): ?int
    {
        return $this->ackWait;
    }

    public function getDeliverGroup(): ?string
    {
        return $this->deliverGroup;
    }

    public function getDeliverPolicy(): string
    {
        return $this->deliverPolicy;
    }

    public function getDeliverSubject(): ?string
    {
        return $this->deliverSubject;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getFlowControl(): ?bool
    {
        return $this->flowControl;
    }

    public function getHeadersOnly(): ?bool
    {
        return $this->headersOnly;
    }

    public function getIdleHeartbeat(): ?int
    {
        return $this->idleHeartbeat;
    }

    public function getMaxAckPending(): ?int
    {
        return $this->maxAckPending;
    }

    public function getMaxDeliver(): ?int
    {
        return $this->maxDeliver;
    }

    public function getMaxWaiting(): ?int
    {
        return $this->maxWaiting;
    }

    public function getStartSequence(): ?int
    {
        return $this->startSequence;
    }

    public function getStartTime(): ?DateTimeInterface
    {
        return $this->startTime;
    }

    public function getReplayPolicy(): string
    {
        return $this->replayPolicy;
    }

    public function isEphemeral(): bool
    {
        return $this->ephemeral;
    }

    /**
     * @deprected
     */
    public function ephemeral(): self
    {
        return $this->setEphemeral(true);
    }

    public function setEphemeral(bool $ephemeral): self
    {
        $this->ephemeral = $ephemeral;
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

    public function setDeliverGroup(?string $deliverGroup): self
    {
        $this->deliverGroup = $deliverGroup;
        return $this;
    }

    public function setDeliverPolicy(string $deliverPolicy): self
    {
        $this->deliverPolicy = DeliverPolicy::validate($deliverPolicy);
        return $this;
    }

    public function setDeliverSubject(?string $deliverSubject): self
    {
        $this->deliverSubject = $deliverSubject;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setFlowControl(?bool $flowControl): self
    {
        $this->flowControl = $flowControl;
        return $this;
    }

    public function setHeadersOnly(?bool $headersOnly): self
    {
        $this->headersOnly = $headersOnly;
        return $this;
    }

    public function setIdleHeartbeat(?int $idleHeartbeat): self
    {
        $this->idleHeartbeat = $idleHeartbeat;
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

    public function setSubjectFilters(array $subjectFilters): self
    {
        $this->subjectFilters = $subjectFilters;
        return $this;
    }

    public function getSubjectFilters(): ?array
    {
        return $this->subjectFilters;
    }

    public function setInactiveThreshold(int $inactiveThresholdNanoSeconds): self
    {
        $this->inactiveThreshold = $inactiveThresholdNanoSeconds;
        return $this;
    }

    public function getInactiveThreshold(): ?int
    {
        return $this->inactiveThreshold;
    }

    public static function fromObject(object $object): static
    {
        $config = $object->config;
        // For ephemeral consumers, durable_name is not set
        // Use name from config if durable_name exists, otherwise null (ephemeral)
        $name = isset($config->durable_name) ? $config->durable_name : null;

        $instance = new static($object->stream_name, $name);

        // Set ephemeral if no durable name
        if ($name === null) {
            $instance->setEphemeral(true);
        }

        $instance->setAckPolicy($config->ack_policy);

        if (isset($config->ack_wait)) {
            $instance->setAckWait($config->ack_wait);
        }

        if (isset($config->deliver_group)) {
            $instance->setDeliverGroup($config->deliver_group);
        }

        $instance->setDeliverPolicy($config->deliver_policy);

        if (isset($config->deliver_subject)) {
            $instance->setDeliverSubject($config->deliver_subject);
        }

        if (isset($config->description)) {
            $instance->setDescription($config->description);
        }

        if (isset($config->flow_control)) {
            $instance->setFlowControl($config->flow_control);
        }

        if (isset($config->headers_only)) {
            $instance->setHeadersOnly($config->headers_only);
        }

        if (isset($config->idle_heartbeat)) {
            $instance->setIdleHeartbeat($config->idle_heartbeat);
        }

        if (isset($config->max_ack_pending)) {
            $instance->setMaxAckPending($config->max_ack_pending);
        }

        if (isset($config->max_deliver)) {
            $instance->setMaxDeliver($config->max_deliver);
        }

        if (isset($config->max_waiting)) {
            $instance->setMaxWaiting($config->max_waiting);
        }

        if (isset($config->replay_policy)) {
            $instance->setReplayPolicy($config->replay_policy);
        }

        if (isset($config->inactive_threshold)) {
            $instance->setInactiveThreshold($config->inactive_threshold);
        }

        // Handle start sequence / start time based on deliver policy
        if (isset($config->opt_start_seq)) {
            $instance->setStartSequence($config->opt_start_seq);
        }

        if (isset($config->opt_start_time)) {
            $startTime = \DateTime::createFromFormat(self::OPT_START_TIME_FORMAT, $config->opt_start_time);
            if ($startTime !== false) {
                $instance->setStartTime($startTime);
            }
        }

        // Handle subject filters (filter_subjects takes precedence over filter_subject)
        if (isset($config->filter_subjects)) {
            $instance->setSubjectFilters($config->filter_subjects);
        } elseif (isset($config->filter_subject)) {
            $instance->setSubjectFilter($config->filter_subject);
        }

        return $instance;
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
            'inactive_threshold' => $this->getInactiveThreshold(),
        ];

        switch ($this->getDeliverPolicy()) {
            case DeliverPolicy::BY_START_SEQUENCE:
                $config['opt_start_seq'] = $this->getStartSequence();
                break;

            case DeliverPolicy::BY_START_TIME:
                $config['opt_start_time'] = $this->getStartTime()?->format(self::OPT_START_TIME_FORMAT);
                break;
        }

        if ($this->getSubjectFilters()) {
            $config['filter_subjects'] = $this->getSubjectFilters();
        } elseif ($this->getSubjectFilter()) {
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

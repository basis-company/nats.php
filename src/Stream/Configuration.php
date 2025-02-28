<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use DomainException;

class Configuration
{
    private array $subjects = [];
    private bool $allowRollupHeaders = true;
    private bool $denyDelete = true;
    private int $maxAge = 0;
    private int $maxConsumers = -1;
    private int $replicas = 1;
    private string $discardPolicy = DiscardPolicy::OLD;
    private string $retentionPolicy = RetentionPolicy::LIMITS;
    private string $storageBackend = StorageBackend::FILE;

    private ?float $duplicateWindow = null;
    private ?int $maxBytes = null;
    private ?int $maxMessageSize = null;
    private ?int $maxMessagesPerSubject = null;
    private ?string $description = null;
    private ?array $consumerLimits = null;

    public function __construct(
        public readonly string $name
    ) {
    }

    public function fromArray(array $array): self
    {
        return $this
            ->setDiscardPolicy($array['discard'])
            ->setMaxConsumers($array['max_consumers'])
            ->setReplicas($array['replicas'] ?? $array['num_replicas'])
            ->setRetentionPolicy($array['retention'])
            ->setStorageBackend($array['storage'])
            ->setSubjects($array['subjects']);
    }

    public function getAllowRollupHeaders(): bool
    {
        return $this->allowRollupHeaders;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDenyDelete(): bool
    {
        return $this->denyDelete;
    }

    public function getDiscardPolicy(): string
    {
        return $this->discardPolicy;
    }

    public function getDuplicateWindow(): ?float
    {
        return $this->duplicateWindow;
    }

    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    public function getMaxBytes(): ?int
    {
        return $this->maxBytes;
    }

    public function getMaxConsumers(): int
    {
        return $this->maxConsumers;
    }

    public function getMaxMessageSize(): ?int
    {
        return $this->maxMessageSize;
    }

    public function getMaxMessagesPerSubject(): ?int
    {
        return $this->maxMessagesPerSubject;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getReplicas(): int
    {
        return $this->replicas;
    }

    public function getRetentionPolicy(): string
    {
        return $this->retentionPolicy;
    }

    public function getStorageBackend(): string
    {
        return $this->storageBackend;
    }

    public function getSubjects(): array
    {
        return $this->subjects;
    }

    public function setAllowRollupHeaders(bool $allowRollupHeaders): self
    {
        $this->allowRollupHeaders = $allowRollupHeaders;
        return $this;
    }

    public function setDenyDelete(bool $denyDelete): self
    {
        $this->denyDelete = $denyDelete;
        return $this;
    }

    public function setDiscardPolicy(string $policy): self
    {
        $this->discardPolicy = DiscardPolicy::validate($policy);
        return $this;
    }

    public function setDuplicateWindow(?float $seconds): self
    {
        $this->duplicateWindow = $seconds;
        return $this;
    }

    /**
     * set the max age in nanoSeconds
     */
    public function setMaxAge(int $maxAgeNanoSeconds): self
    {
        $this->maxAge = $maxAgeNanoSeconds;
        return $this;
    }

    public function setMaxBytes(?int $maxBytes): self
    {
        $this->maxBytes = $maxBytes;
        return $this;
    }

    public function setMaxConsumers(int $maxConsumers): self
    {
        $this->maxConsumers = $maxConsumers;
        return $this;
    }

    public function setMaxMessageSize(?int $maxMessageSize): self
    {
        $this->maxMessageSize = $maxMessageSize;
        return $this;
    }

    public function setMaxMessagesPerSubject(?int $maxMessagesPerSubject): self
    {
        $this->maxMessagesPerSubject = $maxMessagesPerSubject;
        return $this;
    }

    public function setReplicas(int $replicas): self
    {
        $this->replicas = $replicas;
        return $this;
    }

    public function setRetentionPolicy(string $policy): self
    {
        $this->retentionPolicy = RetentionPolicy::validate($policy);
        return $this;
    }

    public function setStorageBackend(string $storage): self
    {
        $this->storageBackend = StorageBackend::validate($storage);
        return $this;
    }

    public function setSubjects(array $subjects): self
    {
        $this->subjects = $subjects;
        return $this;
    }

    public function setConsumerLimits(array $consumerLimits): self
    {
        $this->consumerLimits = ConsumerLimits::validate($consumerLimits);
        return $this;
    }

    public function getConsumerLimits(): ?array
    {
        return $this->consumerLimits;
    }

    public function toArray(): array
    {
        $config = [
            'allow_rollup_hdrs' => $this->getAllowRollupHeaders(),
            'deny_delete' => $this->getDenyDelete(),
            'description' => $this->getDescription(),
            'discard' => $this->getDiscardPolicy(),
            'duplicate_window' => $this->getDuplicateWindow() * 1_000_000_000,
            'max_age' => $this->getMaxAge(),
            'max_bytes' => $this->getMaxBytes(),
            'max_consumers' => $this->getMaxConsumers(),
            'max_msg_size' => $this->getMaxMessageSize(),
            'max_msgs_per_subject' => $this->getMaxMessagesPerSubject(),
            'name' => $this->getName(),
            'num_replicas' => $this->getReplicas(),
            'retention' => $this->getRetentionPolicy(),
            'storage' => $this->getStorageBackend(),
            'subjects' => $this->getSubjects(),
            'consumer_limits' => $this->getConsumerLimits(),
        ];

        foreach ($config as $k => $v) {
            if ($v === null) {
                unset($config[$k]);
            }
        }

        return $config;
    }

    public function validateSubject(string $subject): string
    {
        if (!in_array($subject, $this->getSubjects())) {
            throw new DomainException("Invalid subject $subject");
        }

        return $subject;
    }
}

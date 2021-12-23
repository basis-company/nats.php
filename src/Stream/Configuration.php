<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use Basis\Nats\Client;
use Basis\Nats\Consumer\Consumer;
use DomainException;

class Configuration
{
    private array $subjects = [];
    private int $maxConsumers = -1;
    private string $discardPolicy = DiscardPolicy::OLD;
    private string $retentionPolicy = RetentionPolicy::LIMITS;
    private string $storageBackend = StorageBackend::FILE;
    private int $replicas = 1;

    public function __construct(
        private readonly string $name
    ) {
    }

    public function fromArray(array $array): self
    {
        return $this
            ->setDiscardPolicy($array['discard'])
            ->setMaxConsumers($array['max_consumers'])
            ->setReplicas($array['replicas'])
            ->setRetentionPolicy($array['retention'])
            ->setStorageBackend($array['storage'])
            ->setSubjects($array['subjects']);
    }

    public function getName()
    {
        return strtoupper($this->name);
    }

    public function getDiscardPolicy(): string
    {
        return $this->discardPolicy;
    }

    public function getMaxConsumers(): int
    {
        return $this->maxConsumers;
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

    public function setDiscardPolicy(string $policy): self
    {
        $this->discardPolicy = DiscardPolicy::validate($policy);
        return $this;
    }

    public function setMaxConsumers(int $maxConsumers): self
    {
        $this->maxConsumers = $maxConsumers;
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

    public function toArray(): array
    {
        $config = [
            'name' => $this->getName(),
            'subjects' => $this->getSubjects(),
            'retention' => $this->getRetentionPolicy(),
            'storage' => $this->getStorageBackend(),
            'discard' => $this->getDiscardPolicy(),
            'max_consumers' => $this->getMaxConsumers(),
            'replicas' => $this->getReplicas(),
        ];

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

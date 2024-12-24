<?php

namespace Basis\Nats\Service;

class ServiceGroup
{
    private array $groups = [];

    public function __construct(
        private readonly Service $service,
        private readonly string $name
    ) {
    }

    public function addGroup(string $name): ServiceGroup
    {
        if (!array_key_exists($name, $this->groups)) {
            $this->groups[$name] = new ServiceGroup($this->service, $this->name . '.' . $name);
        }

        return $this->groups[$name];
    }

    public function addEndpoint(
        string $name,
        string|EndpointHandler|callable $serviceHandler,
        array $options = []
    ): void {
        $subject = $this->name . '.' . $name;

        if (array_key_exists('subject', $options)) {
            $subject = $this->name . '.' . $options['subject'];
        }

        $this->service->addEndpoint($name, $serviceHandler, [
            'subject' => $subject,
        ]);
    }
}

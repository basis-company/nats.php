<?php

namespace Basis\Nats\Service\Response;

use Basis\Nats\Service\ServiceEndpoint;

class Info
{
    public string $type = 'io.nats.micro.v1.info_response';
    public array $endpoints;

    public function __construct(
        public string $name,
        public string $id,
        public string $version,
        public string $description,
        /** @var array<ServiceEndpoint> */
        array $endpoints,
    ) {
        $this->endpoints = array_map(self::collect(...), $endpoints);
    }

    public static function collect(ServiceEndpoint $endpoint): array
    {
        return [
            'name' => $endpoint->getName(),
            'subject' => $endpoint->getSubject(),
            'queue_group' => $endpoint->getQueueGroup(),
        ];
    }
}

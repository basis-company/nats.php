<?php

namespace Basis\Nats\Service\Response;

use Basis\Nats\Service\ServiceEndpoint;

class Stats
{
    public string $type = 'io.nats.micro.v1.stats_response';
    public array $endpoints;

    public function __construct(
        public string $name,
        public string $id,
        public string $version,
        public string $started,
        /** @var array<ServiceEndpoint> */
        array $endpoints,
    ) {
        $this->endpoints = array_values(array_map(self::collect(...), $endpoints));
    }

    public static function collect(ServiceEndpoint $endpoint): array
    {
        return [
            'name' => $endpoint->getName(),
            'subject' => $endpoint->getSubject(),
            'queue_group' => $endpoint->getQueueGroup(),
            'num_requests' => $endpoint->getNumRequests(),
            'num_errors' => $endpoint->getNumErrors(),
            'last_error' => $endpoint->getLastError(),
            'processing_time' => $endpoint->getProcessingTime(),
            'average_processing_time' => $endpoint->getAverageProcessingTime(),
        ];
    }
}

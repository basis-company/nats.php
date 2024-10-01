<?php

namespace Tests\Utils;

use Basis\Nats\Message\Payload;
use Basis\Nats\Service\EndpointHandler;

class TestEndpoint implements EndpointHandler
{
    public function handle(Payload $payload): array
    {
        return [
            'success' => true
        ];
    }
}

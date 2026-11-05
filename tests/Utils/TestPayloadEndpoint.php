<?php

namespace Tests\Utils;

use Basis\Nats\Message\Payload;
use Basis\Nats\Service\PayloadEndpointHandler;

class TestPayloadEndpoint implements PayloadEndpointHandler
{
    public function handle(Payload $payload): Payload
    {
        return new Payload(
            body: '{"success":true}',
            headers: ['Nats-Service-Error' => 'example error']
        );
    }
}

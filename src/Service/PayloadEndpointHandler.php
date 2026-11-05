<?php

namespace Basis\Nats\Service;

use Basis\Nats\Message\Payload;

interface PayloadEndpointHandler
{
    public function handle(Payload $payload): Payload;
}

<?php

namespace Basis\Nats\Service;

use Basis\Nats\Message\Payload;

interface EndpointHandler
{
    public function handle(Payload $payload): array;
}

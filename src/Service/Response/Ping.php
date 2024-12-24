<?php

namespace Basis\Nats\Service\Response;

class Ping
{
    public string $type = 'io.nats.micro.v1.ping_response';

    public function __construct(
        public string $name,
        public string $id,
        public string $version,
    ) {
    }
}

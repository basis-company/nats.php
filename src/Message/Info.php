<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Info extends Prototype
{
    public string $server_id;
    public string $server_name;
    public string $version;
    public int $proto;
    public string $git_commit;
    public string $go;
    public string $host;
    public int $port;
    public bool $headers;
    public int $max_payload;
    public bool $jetstream;
    public int $client_id;
    public string $client_ip;

    public ?string $cluster = null;
    public ?array $connect_urls = null;

    public function __toString()
    {
        return 'INFO ' . json_encode($this);
    }
}

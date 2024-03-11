<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Info extends Prototype
{
    public bool $headers;
    public int $max_payload;
    public int $port;
    public int $proto;
    public string $go;
    public string $host;
    public string $server_id;
    public string $server_name;
    public string $version;

    /** @var string[]|null  */
    public ?array $connect_urls;
    /** @var string[]|null  */
    public ?array $ws_connect_urls;
    public ?bool $auth_required;
    public ?bool $cluster_dynamic;
    public ?bool $jetstream;
    public ?bool $ldm;
    public ?bool $tls_available;
    public ?bool $tls_required;
    public ?bool $tls_verify;
    public ?int $client_id;
    public ?string $client_ip;
    public ?string $cluster;
    public ?string $domain;
    public ?string $git_commit;
    public ?string $ip;
    public ?string $nonce;
    public ?string $xkey;

    public function render(): string
    {
        return 'INFO ' . json_encode($this);
    }
}

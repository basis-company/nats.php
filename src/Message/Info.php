<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Info extends Prototype
{
    public string $server_id;
    public string $server_name;
    public string $version;
    public int $proto;
    public ?string $git_commit;
    public string $go;
    public string $host;
    public int $port;
    public bool $headers;
    public ?bool $auth_required;
    public ?bool $tls_required;
    public ?bool $tls_verify;
    public ?bool $tls_available;
    public int $max_payload;
    public ?bool $jetstream;
    public ?string $ip;
    public ?int $client_id;
    public ?string $client_ip;
    public ?string $nonce;
    public ?string $cluster;
    public ?bool $cluster_dynamic;
    public ?string $domain;
    /** @var string[]|null  */
    public ?array $connect_urls;
    /** @var string[]|null  */
    public ?array $ws_connect_urls;
    public ?bool $ldm;
    public ?string $xkey;

    public function render(): string
    {
        return 'INFO ' . json_encode($this);
    }
}

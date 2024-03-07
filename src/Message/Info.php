<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Info extends Prototype
{
    public $server_id;
    public $server_name;
    public $version;
    public $proto;
    public $git_commit;
    public $go;
    public $host;
    public $port;
    public $headers;
    public $auth_required;
    public $tls_required;
    public $tls_verify;
    public $tls_available;
    public $max_payload;
    public $jetstream;
    public $ip;
    public $client_id;
    public $client_ip;
    public $nonce;
    public $cluster;
    public $cluster_dynamic;
    public $domain;
    /** @var string[]|null  */
    public $connect_urls;
    /** @var string[]|null  */
    public $ws_connect_urls;
    public $ldm;
    public $xkey;

    public function render(): string
    {
        return 'INFO ' . json_encode($this);
    }
}

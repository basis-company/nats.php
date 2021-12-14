# Nats client for php
[![License](https://poser.pugx.org/basis-company/nats/license.png)](https://packagist.org/packages/basis-company/nats)
[![Testing](https://github.com/basis-company/nats.php/actions/workflows/phpunit.yml/badge.svg)](https://github.com/basis-company/nats.php/actions/workflows/phpunit.yml)
[![Latest Version](https://img.shields.io/github/release/basis-company/nats.php.svg?style=flat-square)](https://github.com/basis-company/nats.php/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/basis-company/nats.svg?style=flat-square)](https://packagist.org/packages/basis-company/nats)


Feel free to contribute or give any feedback.

- [Installation](#installation)
- [Connection](#connection)
- [Publish Subscribe](#publish-subscribe)
- [Request Response](#request-response)
- [JetStream Api Usage](#jetstream-api-usage)

## Installation
The recommended way to install the library is through [Composer](http://getcomposer.org):
```bash
$ composer require basis-company/nats
```

## Connection
```php
use Basis\Nats\Client;
use Basis\Nats\Configuration;

// this is default options, you can override anyone
$configuration = new Configuration([
    'host' => 'localhost',
    'jwt' => null,
    'lang' => 'php',
    'pass' => null,
    'pedantic' => false,
    'port' => 4222,
    'reconnect' => true,
    'timeout' => 1,
    'token' => null,
    'user' => null,
    'verbose' => false,
    'version' => 'dev',
]);

$client = new Client($configuration);
$client->ping(); // true

```
## Publish Subscribe

```php
$client->subscribe('hello', function ($message) {
    var_dump('got message', $message); // tester
});

$client->publish('hello', 'tester');
$client->processMessage();
```

## Request Response
```php
$client->subscribe('hello.request', function ($name) {
    return "Hello, " . $name;
});

// async interaction
$client->request('hello.request', 'Nekufa1', function ($response) {
    var_dump($response); // Hello, Nekufa1
});

$client->processMessage(); // process request

// sync interaction (block until response get back)
$client->dispatch('hello.request', 'Nekufa2'); // Hello, Nekufa2
```

## JetStream Api Usage
```php
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;

$accountInfo = $client->getApi()->getInfo(); // account_info_response object

$stream = $client->getApi()->getStream('mailer');

$stream->getConfiguration()
    ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
    ->setStorageBackend(StorageBackend::MEMORY)
    ->setSubjects(['mailer.greet', 'mailer.bye']);

$stream->create();

// and put some tasks so workers would be doing something
$stream->put('mailer.greet', 'nekufa@gmail.com');
$stream->put('mailer.bye', 'nekufa@gmail.com');

// this should be set in your worker
$greeter = $stream->getConsumer('greeter');
$greeter->getConfiguration()->setSubjectFilter('mailer.greet');
$greeter->create();
$greeter->handle(function ($address) {
    mail($address, "Hi there!");
});

$goodbyer = $stream->getConsumer('goodbyer');
$goodbyer->getConfiguration()->setSubjectFilter('mailer.buy');
$goodbyer->create();
$goodbyer->handle(function ($address) {
    mail($address, "See you later");
});

```

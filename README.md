# Nats client for php
[![License](https://poser.pugx.org/basis-company/nats/license.png)](https://packagist.org/packages/basis-company/nats)
[![Testing](https://github.com/basis-company/nats.php/actions/workflows/tests.yml/badge.svg)](https://github.com/basis-company/nats.php/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/github/release/basis-company/nats.php.svg)](https://github.com/basis-company/nats.php/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/basis-company/nats.svg)](https://packagist.org/packages/basis-company/nats)


Feel free to contribute or give any feedback.

- [Installation](#installation)
- [Connection](#connection)
- [Publish Subscribe](#publish-subscribe)
- [Request Response](#request-response)
- [JetStream Api Usage](#jetstream-api-usage)
- [Key Value Storage](#key-value-storage)
- [Performance](#performance)

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
$client->process();
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

$client->process(); // process request

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

// stream is created with given configuration
$stream->create();

// and put some tasks so workers would be doing something
$stream->put('mailer.greet', 'nekufa@gmail.com');
$stream->put('mailer.bye', 'nekufa@gmail.com');

// this should be set in your worker
$greeter = $stream->getConsumer('greeter');
$greeter->getConfiguration()->setSubjectFilter('mailer.greet');
// consumer would be created would on first handle call
$greeter->handle(function ($address) {
    mail($address, "Hi there!");
});

$goodbyer = $stream->getConsumer('goodbyer');
$goodbyer->getConfiguration()->setSubjectFilter('mailer.bye');
$goodbyer->create(); // create consumer if you don't want to handle anything right now
$goodbyer->handle(function ($address) {
    mail($address, "See you later");
});

// you can configure batching and iteration count using chain api
$goodbyer
    ->setBatching(2) // how many messages would be requested from nats stream
    ->setIterations(3) // how many times message request should be sent
    ->handle(function () {
        // if you need to break on next iteration simply call interrupt method
        // batch will be processed to the end and the handling would be stopped
        // $goodbyer->interrupt();
    });
```

## Key Value Storage
```php
$bucket = $client->getApi()->getBucket('bucket_name');

// basics
$bucket->put('username', 'nekufa');
echo $bucket->get('username'); // nekufa

// safe update (given revision)
$entry = $bucket->getEntry('username');
echo $entry->value; // nekufa
$bucket->update('username', 'bazyaba', $entry->revision);

// delete value
$bucket->delete('username');

// purge value history
$bucket->purge('username');

// get bucket stats
var_dump($bucket->getStatus());
```


## Performance
Testing on i5-4670k with nats running in docker gives 420k rps for publish and 350k rps for receive in non-verbose mode.

You can run tests on your environment.
```bash
 % wget https://getcomposer.org/download/latest-stable/composer.phar
...
Saving to: ‘composer.phar’

 % ./composer.phar install
Installing dependencies from lock file (including require-dev)
...

 % export NATS_HOST=0.0.0.0
 % export NATS_PORT=4222
 % export NATS_CLIENT_LOG=1
 % vendor/bin/phpunit --filter performance
PHPUnit 9.5.10 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.1.1
Configuration: /home/nekufa/software/github/nats.php/phpunit.xml.dist
Warning:       No code coverage driver available

[2022-01-19T10:42:14.008230+00:00] SubjectTest.testPerformance.INFO: start performance test [] []
[2022-01-19T10:42:14.246606+00:00] SubjectTest.testPerformance.INFO: publishing {"rps":421871.0,"length":100000,"time":0.23703885078430176} []
[2022-01-19T10:42:14.530670+00:00] SubjectTest.testPerformance.INFO: processing {"rps":355120.0,"length":100000,"time":0.2839939594268799} []


 % export NATS_CLIENT_VERBOSE=1
 % vendor/bin/phpunit --filter performance
PHPUnit 9.5.10 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.1.1
Configuration: /home/nekufa/software/github/nats.php/phpunit.xml.dist
Warning:       No code coverage driver available

[2022-01-19T10:42:21.319838+00:00] SubjectTest.testPerformance.INFO: start performance test [] []
[2022-01-19T10:42:21.766501+00:00] SubjectTest.testPerformance.INFO: publishing {"rps":224640.0,"length":100000,"time":0.4451560974121094} []
[2022-01-19T10:42:21.922010+00:00] SubjectTest.testPerformance.INFO: processing {"rps":353317.0,"length":100000,"time":0.15544414520263672} []
.                                                                   1 / 1 (100%)

nekufa@fasiga ~ % cat /proc/cpuinfo | grep i5
model name  : Intel(R) Core(TM) i5-4670K CPU @ 3.40GHz

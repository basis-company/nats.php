<?php

declare(strict_types=1);

namespace Tests\Unit;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Connection;
use Basis\Nats\Message\Prototype as Message;
use Basis\Nats\Message\Subscribe;
use LogicException;
use ReflectionMethod;
use Tests\TestCase;

class SubscriptionReconnectTest extends TestCase
{
    public function testQueueGroupIsRestoredAfterReconnect(): void
    {
        $client = new Client(new Configuration());
        $connection = new class ($client) extends Connection {
            /** @var list<Message> */
            public array $messages = [];

            protected function init(): void
            {
            }

            public function sendMessage(Message $message): void
            {
                $this->messages[] = $message;
            }
        };
        $client->connection = $connection;
        $client->subscribeQueue('subject', 'group', static function (): void {
        });

        $processException = new ReflectionMethod(Connection::class, 'processException');
        $processException->invoke($connection, new LogicException('Simulated disconnect'));

        $restoredSubscription = $connection->messages[1];
        $this->assertInstanceOf(Subscribe::class, $restoredSubscription);
        $this->assertSame(
            'SUB subject group ' . $client->getSubscriptions()[0]['sid'],
            $restoredSubscription->render()
        );
    }
}

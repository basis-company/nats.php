<?php

declare(strict_types=1);


use Tests\FunctionalTestCase;

class AsyncPerformanceTest extends FunctionalTestCase
{
    private int $limit = 100_000;
    private int $counter = 0;

    public function testBackgroundPerformance()
    {
        /** @var \Basis\Nats\AsyncClient $client */
        $client = $this->createClient(['client' => \Basis\Nats\AsyncClient::class])->setDelay(0);
        $client->setLogger(new \Psr\Log\NullLogger());

        $this->logger?->info('start performance test');
        [$left, $right] = \Amp\Sync\createChannelPair();

        $client->subscribe('hello-async', function ($n) use ($left) {
            $this->counter++;
            if($this->counter === $this->limit) {
                $left->send('done');
            }
        });

        $publishing = microtime(true);
        foreach (range(1, $this->limit) as $n) {
            $client->publish('hello-async', 'data-' . $n);
        }
        $publishing = microtime(true) - $publishing;

        $this->logger?->info('publishing', [
            'rps' => floor($this->limit / $publishing),
            'length' => $this->limit,
            'time' => $publishing,
        ]);

        $stop = $client->background(true, 5);

        $processing = microtime(true);
        $right->receive();
        $processing = microtime(true) - $processing;

        $stop();

        $this->logger?->info('processing', [
            'rps' => floor($this->limit / $processing),
            'length' => $this->limit,
            'finished' => $this->counter,
            'time' => $processing,
        ]);

        // at least 5000rps should be enough for test
        $this->assertGreaterThan(5000, $this->limit / $processing);
    }

    public function testForegroundPerformance()
    {
        /** @var \Basis\Nats\AsyncClient $client */
        $client = $this->createClient(['client' => \Basis\Nats\AsyncClient::class])->setDelay(0);
        $client->setLogger(new \Psr\Log\NullLogger());

        $this->logger?->info('start performance test');

        $client->subscribe('hello-async', function ($n) {
            $this->counter++;
        });

        $publishing = microtime(true);
        foreach (range(1, $this->limit) as $n) {
            $client->publish('hello-async', 'data-' . $n);
        }
        $publishing = microtime(true) - $publishing;

        $this->logger?->info('publishing', [
            'rps' => floor($this->limit / $publishing),
            'length' => $this->limit,
            'time' => $publishing,
        ]);

        $processing = microtime(true);
        while ($this->counter < $this->limit) {
            $client->process(0);
        }
        $processing = microtime(true) - $processing;

        $this->logger?->info('processing', [
            'rps' => floor($this->limit / $processing),
            'length' => $this->limit,
            'finished' => $this->counter,
            'time' => $processing,
        ]);

        // at least 5000rps should be enough for test
        $this->assertGreaterThan(5000, $this->limit / $processing);
    }
}

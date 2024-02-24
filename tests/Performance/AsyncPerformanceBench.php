<?php

declare(strict_types=1);


use Tests\FunctionalTestCase;

/**
 * @BeforeMethods("setc")
 */
class AsyncPerformanceBench extends FunctionalTestCase
{
    protected \Basis\Nats\Client|null $client;
    private bool $received = false;
    private \Amp\Sync\Channel|null $channel = null;
    protected string $subject;

    public function setc(array $params): void
    {
        parent::setup();
        $client = $this->createClient(['client' => match($params['client']) {
            'async-background', 'async-process' => \Basis\Nats\AmpClient::class,
            default => \Basis\Nats\Client::class,
        }])->setDelay(0);
        $client->setLogger(new \Psr\Log\NullLogger());
        $this->client = $client;
        $this->subject = md5(uniqid('', true));
        $sender = null;
        if($params['client'] === 'async-background') {
            [$sender, $this->channel] = \Amp\Sync\createChannelPair();
        }
        $received = 0;
        $client->subscribe($this->subject, function () use ($sender, &$received, $params) {
            if(++$received >= $params['messages']) {
                $this->received = true;
                if ($this->channel !== null) {
                    $sender->send('done');
                }
            }
        });

        for($i = 0; $i < $params['messages']; $i++) {
            $client->publish($this->subject, 'data');
        }
    }

    /**
     * @Iterations(100)
     * @ParamProviders({"provideClient", "messagesToReceive"})
     */
    public function benchPerformance($params): void
    {
        if($params['client'] === 'async-background') {
            $this->client->background(true, 50);
        }

        if($this->channel) {
            $this->channel->receive();
        } else {
            while (!$this->received) {
                $this->client->process();
            }
        }
    }

    public function provideClient(): Generator
    {
        yield ['client' => 'async-background'];
        yield ['client' => 'async-process'];
        yield ['client' => 'sync'];
    }

    public function messagesToReceive(): Generator
    {
        yield ['messages' => 1];
        yield ['messages' => 1_000];
        yield ['messages' => 5_000];
    }
}

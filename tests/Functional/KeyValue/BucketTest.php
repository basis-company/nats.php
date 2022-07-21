<?php

declare(strict_types=1);

namespace Tests\Functional\KeyValue;

use Tests\FunctionalTestCase;

class BucketTest extends FunctionalTestCase
{
    public function testBasics()
    {
        $bucket = $this->createClient()
            ->getApi()
            ->getBucket('test_bucket');

        $this->assertSame(0, $bucket->getStatus()->values);

        $bucket->put('username', 'nekufa');

        $this->assertSame(1, $bucket->getStatus()->values);
        $this->assertSame($bucket->get('username'), 'nekufa');

        // invalid update
        $bucket->update('username', 'bazyaba', 100_500);
        $this->assertSame($bucket->get('username'), 'nekufa');
        $this->assertSame(1, $bucket->getStatus()->values);

        $bucket->update('username', 'bazyaba', $bucket->getEntry('username')->revision);
        $this->assertSame($bucket->get('username'), 'bazyaba');
        $this->assertSame(2, $bucket->getStatus()->values);

        $bucket->delete('username');

        // username null value in history
        $this->assertSame($bucket->get('username'), null);
        $this->assertSame(3, $bucket->getStatus()->values);

        // purge key logs
        $bucket->purge('username');

        // username purged
        $this->assertSame($bucket->get('username'), null);
        $this->assertSame(1, $bucket->getStatus()->values);

        $bucket->put('service_handlers', json_encode([
            [
                'threads' => 2,
                'subject' => 'tester',
                'name' => 'tester',
            ]
        ]));

        $this->assertCount(1, json_decode($bucket->get('service_handlers')));
    }
}

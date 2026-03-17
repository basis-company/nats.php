<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\ReplayPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Tests\FunctionalTestCase;

/**
 * Functional tests comparing JetStream message acknowledgement types.
 *
 * These tests run against a live NATS server (started via docker/) and verify
 * the redelivery behaviour of the three acknowledgement types:
 *
 *  - ACK  (+ACK)  — successful processing, message is removed, no redelivery.
 *  - NACK (-NAK)  — temporary failure, message is redelivered after a delay.
 *  - TERM (+TERM) — permanent failure, message is removed, no redelivery.
 *
 * Each test publishes 3 messages, consumes them, responds with the respective
 * acknowledgement, then asserts whether or not the server redelivers them.
 *
 * Consumer ack_wait is intentionally set short (1s for ACK/TERM, 5s for NACK)
 * so that the tests can verify redelivery windows within a reasonable time.
 */
class TermTest extends FunctionalTestCase
{
    /**
     * ACK: message is acknowledged and should NOT be redelivered.
     *
     * This serves as the control case — after a successful ACK the server
     * considers the message fully processed. It must not appear again even
     * after the ack_wait window (1 s) has elapsed.
     */
    public function testAckPreventsRedelivery()
    {
        $client = $this->createClient();

        // Create a stream with INTEREST retention so messages are kept only
        // as long as at least one consumer has not yet acknowledged them.
        $stream = $client->getApi()->getStream('term_ack');
        $stream->getConfiguration()
            ->setSubjects(['term.ack'])
            ->setRetentionPolicy(RetentionPolicy::INTEREST);
        $stream->create();

        // Consumer with explicit ACK policy and a short ack_wait (1 s)
        // so we can verify that no redelivery happens after the window.
        $consumer = $stream->getConsumer('term_ack');
        $consumer->setExpires(5)->setBatching(3);
        $consumer->getConfiguration()
            ->setSubjectFilter('term.ack')
            ->setReplayPolicy(ReplayPolicy::INSTANT)
            ->setAckPolicy(AckPolicy::EXPLICIT)
            ->setAckWait(1_000_000_000); // 1s ack wait
        $consumer->create();

        // Publish 3 messages to the stream
        $stream->publish('term.ack', 'message1');
        $stream->publish('term.ack', 'message2');
        $stream->publish('term.ack', 'message3');

        $this->assertSame(3, $consumer->info()->num_pending);

        $queue = $consumer->getQueue();

        // Fetch and ACK all three messages — standard successful processing
        $queue->setTimeout(0.5);
        $messages = $queue->fetchAll(3);
        $this->assertCount(3, $messages);

        foreach ($messages as $message) {
            $message->ack();
        }

        // Allow the server a moment to process the ACKs
        usleep(200_000);

        // Both counters should be zero: nothing pending, nothing awaiting ACK
        $this->assertSame(0, $consumer->info()->num_pending);
        $this->assertSame(0, $consumer->info()->num_ack_pending);

        // Wait beyond the ack_wait (1.2 s > 1 s) and confirm nothing is redelivered
        usleep(1_200_000);
        $queue->setTimeout(0.2);
        $messages = $queue->fetchAll();
        $this->assertCount(0, $messages);
    }

    /**
     * NACK: message is negatively acknowledged and SHOULD be redelivered.
     *
     * After a NACK with a 0.5 s delay the server schedules the messages for
     * redelivery. The test confirms that:
     *  1. Before the delay elapses — no messages are available.
     *  2. After the delay elapses  — all 3 messages are redelivered.
     *
     * The ack_wait is set to 5 s (much longer than the nack delay) so that
     * any redelivery we observe is caused by the NACK, not by an ack timeout.
     */
    public function testNackCausesRedelivery()
    {
        $client = $this->createClient();

        // INTEREST retention keeps messages until every consumer has ACKed them.
        $stream = $client->getApi()->getStream('term_nack');
        $stream->getConfiguration()
            ->setSubjects(['term.nack'])
            ->setRetentionPolicy(RetentionPolicy::INTEREST);
        $stream->create();

        // Long ack_wait (5 s) ensures the ack-wait timer does not interfere
        // with the nack-delay-based redelivery we are testing.
        $consumer = $stream->getConsumer('term_nack');
        $consumer->setExpires(5)->setBatching(3);
        $consumer->getConfiguration()
            ->setSubjectFilter('term.nack')
            ->setReplayPolicy(ReplayPolicy::INSTANT)
            ->setAckPolicy(AckPolicy::EXPLICIT)
            ->setAckWait(5_000_000_000); // 5s — deliberately long
        $consumer->create();

        // Publish 3 messages to the stream
        $stream->publish('term.nack', 'message1');
        $stream->publish('term.nack', 'message2');
        $stream->publish('term.nack', 'message3');

        $this->assertSame(3, $consumer->info()->num_pending);

        $queue = $consumer->getQueue();
        $queue->setTimeout(0.5);
        $messages = $queue->fetchAll(3);
        $this->assertCount(3, $messages);

        // NACK every message with a 0.5 s delay — the server will schedule
        // them for redelivery after that delay.
        foreach ($messages as $message) {
            $message->nack(0.5);
        }

        // Allow the server a moment to process the NACKs
        usleep(200_000);

        // All 3 are now awaiting redelivery (ack_pending), none are "new" (pending)
        $this->assertSame(3, $consumer->info()->num_ack_pending);
        $this->assertSame(0, $consumer->info()->num_pending);

        // Before the 0.5 s nack delay: the messages should NOT be available yet
        $queue->setTimeout(0.2);
        $messages = $queue->fetchAll();
        $this->assertCount(0, $messages);

        // Wait for the nack delay (0.5 s) to elapse
        usleep(700_000);

        // Now the server redelivers all three messages
        $queue->setTimeout(1.0);
        $messages = $queue->fetchAll(3);
        $this->assertCount(3, $messages);

        // ACK them to clean up
        foreach ($messages as $message) {
            $message->ack();
        }

        usleep(200_000);
        $this->assertSame(0, $consumer->info()->num_pending);
        $this->assertSame(0, $consumer->info()->num_ack_pending);
    }

    /**
     * TERM: message is terminally rejected and should NOT be redelivered.
     *
     * Unlike NACK, TERM tells the server the message is permanently
     * unprocessable (e.g. malformed payload, unsupported schema version).
     * The server immediately removes the message from the consumer's
     * pending set — both num_pending and num_ack_pending drop to zero
     * right away, and the message never comes back even after the
     * ack_wait window (1 s) has passed.
     *
     * This is the key difference between TERM and NACK:
     *  - NACK → server redelivers (see testNackCausesRedelivery).
     *  - TERM → server discards, no redelivery ever.
     */
    public function testTermPreventsRedelivery()
    {
        $client = $this->createClient();

        // INTEREST retention — messages live only while a consumer needs them.
        $stream = $client->getApi()->getStream('term_term');
        $stream->getConfiguration()
            ->setSubjects(['term.term'])
            ->setRetentionPolicy(RetentionPolicy::INTEREST);
        $stream->create();

        // Short ack_wait (1 s) so we can verify that even after it elapses
        // the TERMed messages do not come back.
        $consumer = $stream->getConsumer('term_term');
        $consumer->setExpires(5)->setBatching(3);
        $consumer->getConfiguration()
            ->setSubjectFilter('term.term')
            ->setReplayPolicy(ReplayPolicy::INSTANT)
            ->setAckPolicy(AckPolicy::EXPLICIT)
            ->setAckWait(1_000_000_000); // 1s ack wait
        $consumer->create();

        // Publish 3 messages to the stream
        $stream->publish('term.term', 'message1');
        $stream->publish('term.term', 'message2');
        $stream->publish('term.term', 'message3');

        $this->assertSame(3, $consumer->info()->num_pending);

        $queue = $consumer->getQueue();
        $queue->setTimeout(0.5);
        $messages = $queue->fetchAll(3);
        $this->assertCount(3, $messages);

        // TERM every message with a reason — the server should immediately
        // remove them from the consumer's pending set, no redelivery.
        foreach ($messages as $message) {
            $message->term('unprocessable content');
        }

        // Allow the server a moment to process the TERMs
        usleep(200_000);

        // Both counters should be zero immediately — unlike NACK where
        // num_ack_pending stays non-zero until the messages are redelivered.
        $this->assertSame(0, $consumer->info()->num_pending);
        $this->assertSame(0, $consumer->info()->num_ack_pending);

        // Wait beyond the ack_wait (1.2 s > 1 s) and confirm the messages
        // are truly gone — no redelivery occurs.
        usleep(1_200_000);
        $queue->setTimeout(0.2);
        $messages = $queue->fetchAll();
        $this->assertCount(0, $messages);
    }
}

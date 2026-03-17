<?php

namespace Tests\Unit\Message;

use Basis\Nats\Message\Term;
use Tests\TestCase;

/**
 * Unit tests for the Term message wire format.
 *
 * Verifies that the Term message renders the correct NATS protocol output
 * for both bare termination (+TERM) and termination with a reason string.
 * These are outgoing-only messages published to the JetStream reply-to subject.
 */
class TermTest extends TestCase
{
    /**
     * A bare TERM without a reason should render as "+TERM" (5 bytes payload).
     * This tells the server to stop redelivering the message without explanation.
     */
    public function testTerm()
    {
        // The subject is the JetStream reply-to address from the original message
        $term = new Term([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0'
        ]);

        // Expected wire format: PUB <subject>  5\r\n+TERM
        // The "5" is the byte length of "+TERM"
        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  5\r\n+TERM", $term->render());
    }

    /**
     * When a reason is provided, it should be appended after "+TERM " in the payload.
     * The reason is a human-readable explanation of why the message was terminated,
     * useful for debugging and observability.
     */
    public function testTermWithReason()
    {
        $term = new Term([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0',
            'reason' => 'invalid message',
        ]);

        // Expected wire format: PUB <subject>  21\r\n+TERM invalid message
        // The "21" is the byte length of "+TERM invalid message"
        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  21\r\n+TERM invalid message", $term->render());
    }
}

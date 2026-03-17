<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

/**
 * JetStream Term acknowledgement message.
 *
 * Signals the server that the message is terminally unprocessable and should
 * never be redelivered.
 *
 * Use TERM when the message content is fundamentally invalid (e.g. malformed
 * payload, schema violation, unsupported version) and retrying would never
 * succeed. The server removes the message from the consumer's pending set
 * immediately, without waiting for the ack-wait timeout.
 *
 * Wire format:  PUB <replyTo> <len>\r\n+TERM [reason]
 *
 * @see https://docs.nats.io/using-nats/developer/anatomy
 */
class Term extends Prototype
{
    /** @var string The JetStream reply-to subject (e.g. $JS.ACK.<stream>.<consumer>...). */
    public string $subject;

    /** @var string Optional human-readable reason why the message was terminated. */
    public string $reason;

    public function render(): string
    {
        $data = ['+TERM'];
        if (isset($this->reason) && $this->reason !== '') {
            $data[] = $this->reason;
        }
        $payload = Payload::parse(implode(' ', $data))->render();
        return "PUB $this->subject  $payload";
    }
}

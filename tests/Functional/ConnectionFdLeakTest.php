<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Connection;
use RuntimeException;
use Tests\FunctionalTestCase;

/**
 * Breaks the connection ITERATIONS times and asserts that the process
 * does not leak file descriptors.
 *
 * Each iteration:
 *  - a connected socket pair stands in for the server; the test plays the
 *    server (sends the INFO line, then closes its end — a real feof on
 *    the client's end, simulating a server-side disconnect / NATS restart)
 *  - the old socket is retained (simulating a long-running process where
 *    old sockets are still referenced, so PHP does not auto-close them)
 *  - the client detects the broken connection and reconnects to the real
 *    NATS server from the docker-compose stack (docker/docker-compose.yml
 *    must be up) via Connection::processException
 *
 * Without an explicit `fclose` in Connection::processException, every
 * reconnection leaks the old socket's file descriptor. After 1000 broken
 * connections the process crosses PHP's FD_SETSIZE limit (1024) and
 * stream_select() reports:
 *
 *   You MUST recompile PHP with a larger value of FD_SETSIZE.
 *   It is set to 1024, but you have descriptors numbered at least as high as N.
 *
 * Run: vendor/bin/phpunit --no-coverage tests/Functional/ConnectionFdLeakTest.php
 */
class ConnectionFdLeakTest extends FunctionalTestCase
{
    private const ITERATIONS = 1000;
    /** Tolerable growth of open FDs over the whole loop. */
    private const MAX_FD_GROWTH = 16;
    /**
     * Number of pre-opened (retained) file descriptors, simulating a
     * long-running process with many open files. Chosen so that 1000
     * leaked sockets push the highest FD number past PHP's FD_SETSIZE
     * limit (1024) and make stream_select() report the error.
     */
    private const PRE_OPENED_FDS = 60;

    /**
     * Sockets of broken (server-closed) connections, retained to simulate
     * a long-running process where old sockets are still referenced and
     * therefore are NOT auto-closed by PHP's resource refcounting.
     * @var array<int, resource>
     */
    private array $retainedSockets = [];

    /**
     * File descriptors opened before the loop, retained for the whole test.
     * @var array<int, resource>
     */
    private array $preOpenedFds = [];

    public function testNoFdLeakAfter1000BrokenConnections(): void
    {
        $client = $this->createClient([
            'reconnect' => true,
            // bounded so a dead NATS server fails the test instead of hanging
            'maxReconnectAttempts' => 5,
            // tiny constant delay so 1000 reconnections don't stall the run
            'delay' => 0.001,
            'delayMode' => 'constant',
        ]);

        // Simulate a long-running process that already holds open files:
        // these are counted into `before`, so the leaked sockets push the
        // highest FD number past the FD_SETSIZE limit (1024).
        for ($i = 0; $i < self::PRE_OPENED_FDS; $i++) {
            $this->preOpenedFds[] = fopen('/dev/null', 'r');
        }

        $before = self::countOpenFileDescriptors();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // A connected socket pair plays the role of a NATS server:
            // the test owns both ends, so a disconnect is a real feof.
            // PHP 8.5: stream_socket_pair(domain, type, protocol) — AF_UNIX (1), SOCK_STREAM (1).
            [$clientEnd, $serverEnd] = stream_socket_pair(1, 1, 0);
            self::setSocket($client->connection, $clientEnd);

            // Server side: greet the client with the INFO line
            fwrite($serverEnd, 'INFO {"server_id":"fd-leak-test"}' . "\r\n");
            $client->process(0);

            // Server-side disconnect: close the server end (like a NATS restart)
            fclose($serverEnd);

            // Retain the broken (server-dead) socket: in a long-running
            // process old sockets are still referenced, so PHP does not
            // auto-close them. Without an explicit fclose in
            // Connection::processException each one leaks its file descriptor.
            $this->retainedSockets[] = $clientEnd;

            // Detect the broken connection and reconnect to the real NATS
            // server (docker-compose stack) via Connection::processException
            $client->process(0);
        }

        $after = self::countOpenFileDescriptors();
        $leaked = $after - $before;

        $this->assertLessThanOrEqual(
            self::MAX_FD_GROWTH,
            $leaked,
            sprintf(
                "File descriptor leak after %d broken connections: before=%d, after=%d, leaked=%d. " .
                "Each broken connection must explicitly close the old socket " .
                "(Connection::processException nulls the socket reference without fclose), " .
                "otherwise the process crosses PHP's FD_SETSIZE limit (1024) and " .
                "stream_select() starts reporting 'You MUST recompile PHP with a " .
                "larger value of FD_SETSIZE'.",
                self::ITERATIONS, $before, $after, $leaked
            )
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->retainedSockets = [];
        $this->preOpenedFds = [];
    }

    /** Set the private `socket` property via reflection. */
    private static function setSocket(Connection $connection, mixed $value): void
    {
        $reflection = new \ReflectionClass($connection);
        $property = $reflection->getProperty('socket');
        $property->setValue($connection, $value);
    }

    private static function countOpenFileDescriptors(): int
    {
        if (!is_dir('/proc/self/fd')) {
            throw new RuntimeException(
                'This test requires Linux (/proc/self/fd) to count open file descriptors.'
            );
        }

        return count(scandir('/proc/self/fd'));
    }
}

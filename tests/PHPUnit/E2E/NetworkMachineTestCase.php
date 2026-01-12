<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E;

use Noem\State\Test\E2E\Support\MockConnection;
use Noem\State\Test\E2E\Support\MockSocket;

/**
 * Base test case for machines with network operations.
 *
 * Provides mock socket infrastructure for testing without real I/O.
 */
abstract class NetworkMachineTestCase extends AsyncMachineTestCase
{
    protected MockSocket $mockSocket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSocket = MockSocket::createServer('tcp://0.0.0.0:8080');

        // Load test version of ServerConnection (defines global \ServerConnection class)
        if (!class_exists('ServerConnection', false)) {
            require_once __DIR__ . '/Support/ServerConnection.php';
        }
    }

    /**
     * Create and queue a mock HTTP connection.
     *
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param array $headers HTTP headers
     * @param string $body Request body
     * @return MockConnection The queued connection
     */
    protected function queueHttpRequest(
        string $method = 'GET',
        string $uri = '/',
        array $headers = [],
        string $body = ''
    ): MockConnection {
        $connection = MockConnection::createHttpRequest($method, $uri, $headers, $body);
        $this->mockSocket->queueConnection($connection);

        return $connection;
    }

    /**
     * Assert that a mock connection has been closed.
     *
     * @param MockConnection $connection
     * @param string $message
     */
    protected function assertConnectionClosed(MockConnection $connection, string $message = ''): void
    {
        $this->assertTrue(
            $connection->isClosed(),
            $message ?: 'Expected connection to be closed'
        );
    }

    /**
     * Assert that a mock connection is still open.
     *
     * @param MockConnection $connection
     * @param string $message
     */
    protected function assertConnectionOpen(MockConnection $connection, string $message = ''): void
    {
        $this->assertFalse(
            $connection->isClosed(),
            $message ?: 'Expected connection to be open'
        );
    }

    /**
     * Assert that data was written to a connection.
     *
     * @param MockConnection $connection
     * @param string $expected Expected data (or substring)
     * @param string $message
     */
    protected function assertConnectionReceived(
        MockConnection $connection,
        string $expected,
        string $message = ''
    ): void {
        $written = $connection->getWrittenData();
        $this->assertStringContainsString(
            $expected,
            $written,
            $message ?: "Expected connection to receive '{$expected}'"
        );
    }

    /**
     * Assert that the mock server socket is non-blocking.
     *
     * @param string $message
     */
    protected function assertSocketNonBlocking(string $message = ''): void
    {
        $this->assertFalse(
            $this->mockSocket->isBlocking(),
            $message ?: 'Expected socket to be non-blocking'
        );
    }

    /**
     * Get the mock socket for direct manipulation.
     *
     * @return MockSocket
     */
    protected function getMockSocket(): MockSocket
    {
        return $this->mockSocket;
    }
}

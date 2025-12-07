<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\Support;

/**
 * Mock socket for testing network operations without real I/O.
 *
 * Simulates stream_socket_* functions with controllable behavior.
 */
class MockSocket
{
    private bool $blocking = true;
    private bool $closed = false;
    private array $pendingConnections = [];
    private array $activeClients = [];

    /**
     * Create a mock server socket.
     */
    public static function createServer(string $address): self
    {
        return new self();
    }

    /**
     * Set blocking mode.
     */
    public function setBlocking(bool $blocking): void
    {
        $this->blocking = $blocking;
    }

    /**
     * Check if socket is blocking.
     */
    public function isBlocking(): bool
    {
        return $this->blocking;
    }

    /**
     * Queue a pending connection.
     */
    public function queueConnection(MockConnection $connection): void
    {
        $this->pendingConnections[] = $connection;
    }

    /**
     * Accept a connection (returns null if non-blocking and no connections).
     */
    public function accept(): ?MockConnection
    {
        if (empty($this->pendingConnections)) {
            return null;
        }

        $connection = array_shift($this->pendingConnections);
        $this->activeClients[spl_object_id($connection)] = $connection;

        return $connection;
    }

    /**
     * Get all active client connections.
     *
     * @return MockConnection[]
     */
    public function getActiveClients(): array
    {
        return array_values($this->activeClients);
    }

    /**
     * Close the server socket.
     */
    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * Check if socket is closed.
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Get count of pending connections.
     */
    public function getPendingConnectionCount(): int
    {
        return count($this->pendingConnections);
    }
}

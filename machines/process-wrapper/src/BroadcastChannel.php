<?php

declare(strict_types=1);

namespace ProcessWrapper;

/**
 * Manages broadcast to multiple connected clients via Unix domain socket
 */
class BroadcastChannel
{
    private string $name;

    /** @var resource */
    private mixed $serverSocket;

    /** @var resource[] */
    private array $clients = [];

    public function __construct(string $name, mixed $serverSocket)
    {
        $this->name = $name;
        $this->serverSocket = $serverSocket;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Accept new connections and clean up disconnected clients
     */
    public function tick(): void
    {
        // Accept new connections
        while (($client = @stream_socket_accept($this->serverSocket, 0)) !== false) {
            stream_set_blocking($client, false);
            $this->clients[] = $client;
        }

        // Remove disconnected clients
        $this->clients = array_filter(
            $this->clients,
            fn($c) => is_resource($c) && !feof($c)
        );
    }

    /**
     * Broadcast data to all connected clients
     */
    public function broadcast(string $data): int
    {
        $sent = 0;

        foreach ($this->clients as $key => $client) {
            if (!is_resource($client)) {
                unset($this->clients[$key]);
                continue;
            }

            $result = @fwrite($client, $data);
            if ($result === false) {
                // Client disconnected
                @fclose($client);
                unset($this->clients[$key]);
            } else {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Get number of connected clients
     */
    public function clientCount(): int
    {
        return count($this->clients);
    }

    /**
     * Close all client connections
     */
    public function close(): void
    {
        foreach ($this->clients as $client) {
            if (is_resource($client)) {
                @fclose($client);
            }
        }
        $this->clients = [];
    }
}

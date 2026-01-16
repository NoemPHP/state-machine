<?php

declare(strict_types=1);

namespace ProcessWrapper;

/**
 * Bidirectional control channel - accepts input from any client, broadcasts output to all
 */
class ControlChannel
{
    /** @var resource */
    private mixed $serverSocket;

    /** @var resource[] */
    private array $clients = [];

    /** @var string[] Input buffer per client */
    private array $inputBuffers = [];

    public function __construct(mixed $serverSocket)
    {
        $this->serverSocket = $serverSocket;
    }

    /**
     * Accept new connections, read input from clients, clean up disconnected
     *
     * @return string[] Array of complete input lines from all clients
     */
    public function tick(): array
    {
        $input = [];

        // Accept new connections
        while (($client = @stream_socket_accept($this->serverSocket, 0)) !== false) {
            stream_set_blocking($client, false);
            $id = (int)$client;
            $this->clients[$id] = $client;
            $this->inputBuffers[$id] = '';
        }

        // Read from all clients
        foreach ($this->clients as $id => $client) {
            if (!is_resource($client)) {
                unset($this->clients[$id], $this->inputBuffers[$id]);
                continue;
            }

            $data = @fread($client, 8192);

            if ($data === false || ($data === '' && feof($client))) {
                // Client disconnected
                @fclose($client);
                unset($this->clients[$id], $this->inputBuffers[$id]);
                continue;
            }

            if ($data !== '') {
                $this->inputBuffers[$id] .= $data;

                // Extract complete lines
                while (($pos = strpos($this->inputBuffers[$id], "\n")) !== false) {
                    $line = substr($this->inputBuffers[$id], 0, $pos + 1);
                    $this->inputBuffers[$id] = substr($this->inputBuffers[$id], $pos + 1);
                    $input[] = $line;
                }
            }
        }

        return $input;
    }

    /**
     * Broadcast output to all connected clients
     */
    public function broadcast(string $data): int
    {
        $sent = 0;

        foreach ($this->clients as $id => $client) {
            if (!is_resource($client)) {
                unset($this->clients[$id], $this->inputBuffers[$id]);
                continue;
            }

            $result = @fwrite($client, $data);
            if ($result === false) {
                @fclose($client);
                unset($this->clients[$id], $this->inputBuffers[$id]);
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
     * Close all connections
     */
    public function close(): void
    {
        foreach ($this->clients as $client) {
            if (is_resource($client)) {
                @fclose($client);
            }
        }
        $this->clients = [];
        $this->inputBuffers = [];
    }
}

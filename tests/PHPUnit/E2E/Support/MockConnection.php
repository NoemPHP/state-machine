<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\Support;

/**
 * Mock client connection for testing request/response cycles.
 */
class MockConnection
{
    private bool $closed = false;
    private string $readBuffer = '';
    private string $writeBuffer = '';
    private int $readPosition = 0;

    public function __construct(
        private readonly string $request
    ) {
        $this->readBuffer = $request;
    }

    /**
     * Create a mock HTTP GET request.
     */
    public static function createHttpRequest(
        string $method = 'GET',
        string $uri = '/',
        array $headers = [],
        string $body = ''
    ): self {
        $request = "{$method} {$uri} HTTP/1.1\r\n";

        foreach ($headers as $name => $value) {
            $request .= "{$name}: {$value}\r\n";
        }

        $request .= "\r\n{$body}";

        return new self($request);
    }

    /**
     * Read data from connection (simulates fread).
     */
    public function read(int $length): string|false
    {
        if ($this->closed) {
            return '';
        }

        if ($this->readPosition >= strlen($this->readBuffer)) {
            return '';
        }

        $chunk = substr($this->readBuffer, $this->readPosition, $length);
        $this->readPosition += strlen($chunk);

        return $chunk;
    }

    /**
     * Write data to connection (simulates fwrite).
     */
    public function write(string $data): int|false
    {
        if ($this->closed) {
            return false;
        }

        $this->writeBuffer .= $data;

        return strlen($data);
    }

    /**
     * Close the connection.
     */
    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * Check if connection is closed.
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Check if end of stream reached.
     */
    public function isEof(): bool
    {
        return $this->closed || $this->readPosition >= strlen($this->readBuffer);
    }

    /**
     * Get all data written to this connection.
     */
    public function getWrittenData(): string
    {
        return $this->writeBuffer;
    }

    /**
     * Get the original request.
     */
    public function getRequest(): string
    {
        return $this->request;
    }

    /**
     * Get remote address (simulates stream_socket_get_name).
     */
    public function getRemoteAddress(): string
    {
        return '127.0.0.1:' . rand(10000, 65535);
    }
}

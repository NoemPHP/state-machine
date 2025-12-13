<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\Support;

/**
 * Test version of ServerConnection that wraps MockConnection.
 *
 * This class is defined in the global namespace (via class_alias below)
 * to match the production ServerConnection from machines/webserver/src/ServerConnection.php
 */
class ServerConnection
{
    public readonly string $method;

    public readonly string $uri;

    public readonly string $protocol;

    public function __construct(public readonly mixed $client, public readonly string $request)
    {
        // Parse HTTP request
        $lines = explode("\r\n", $request);
        $requestLine = $lines[0];
        $parts = explode(' ', $requestLine);

        if (count($parts) < 3) {
            return;
        }
        $this->method = $parts[0];
        $this->uri = $parts[1];
        $this->protocol = $parts[2];
    }
}

// Create global alias so tests using \ServerConnection will use this class
if (!class_exists('ServerConnection', false)) {
    class_alias(ServerConnection::class, 'ServerConnection');
}

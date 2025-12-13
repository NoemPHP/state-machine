<?php

declare(strict_types=1);

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

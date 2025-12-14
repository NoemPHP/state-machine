<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\WebServer\Integration;

use Noem\State\Test\E2E\Support\MockSocket;

/**
 * Shared callbacks for webserver integration tests.
 *
 * Provides mock-compatible versions of production callbacks.
 */
trait WebServerIntegrationTestTrait
{
    /**
     * Get mock-compatible container callbacks for webserver tests.
     *
     * @param MockSocket $mockSocket
     * @param array $overrides Optional callback overrides
     * @return array
     */
    protected function getWebServerCallbacks(MockSocket $mockSocket, array $overrides = []): array
    {
        $defaults = [
            'server.starting.onEnter' => function (object $trigger) use ($mockSocket) {
                $mockSocket->setBlocking(false);
                $this->set('server', $mockSocket);
            },

            'server.starting.accept' => function (object $trigger) use ($mockSocket) {
                $server = $this->get('server');
                $clients = [];

                yield;
                while (true) {
                    // Clean up closed clients (mimics production fix in container.php:46-54)
                    foreach ($clients as $clientId => $client) {
                        if ($client->isClosed()) {
                            unset($clients[$clientId]);
                        }
                    }

                    // Check for new connections
                    $newClient = $mockSocket->accept();
                    if ($newClient) {
                        $clientId = spl_object_id($newClient);
                        $clients[$clientId] = $newClient;

                        // Read complete request
                        $request = $newClient->getRequest();
                        $this->dispatch(new \ServerConnection($newClient, $request));
                    }

                    yield;
                }
            },

            'request.action.accept' => function (\ServerConnection $connection) {
                $client = $connection->client;
                $request = $connection->request;

                // Parse request headers and body
                $parts = explode("\r\n\r\n", $request, 2);
                $head = $parts[0];
                $body = $parts[1] ?? '';

                $headerLines = explode("\r\n", $head);
                $headers = [];
                foreach ($headerLines as $headerLine) {
                    if (strpos($headerLine, ':') !== false) {
                        [$key, $value] = explode(':', $headerLine, 2);
                        $headers[trim($key)] = trim($value);
                    }
                }

                $this->set('client', $client);
                $this->set('headers', $headers);
                $this->set('body', $body);
                $this->set('uri', $connection->uri);
            },

            'request.action.processing' => function (object $trigger) {
                $client = $this->get('client');

                // Check if client is valid
                if (!$client || $client->isClosed()) {
                    yield; // Must be generator for async
                    return;
                }

                // Write response headers
                $response = sprintf(
                    "HTTP/1.1 200 OK\r\nConnection: close\r\nContent-Type: text/html\r\nDate: %s\r\n\r\n",
                    gmdate('r')
                );

                $client->write($response);
                yield; // Yield for async cooperation

                // Write simple body (template rendering tested separately)
                $bodyTemplate = $this->get('bodyTemplate');
                if ($bodyTemplate) {
                    $client->write($bodyTemplate);
                    yield;
                }

                $this->set('close', true);
                yield;
            },

            'request.onEnter.close' => function (object $trigger) {
                $client = $this->get('client');
                if ($client && !$client->isClosed()) {
                    $client->close();
                }
            },

            'transition.guard.processing.close' => function (object $trigger): bool {
                $close = $this->get('close');
                return $close === true;
            },
        ];

        return array_merge($defaults, $overrides);
    }
}

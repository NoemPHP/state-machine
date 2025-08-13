<?php

declare(strict_types=1);

return [
    'server.starting.onEnter' => function (object $trigger) {
        // Set up the socket server on port 8080
        $ctx = stream_context_create([
            'socket' => [
                'backlog' => 511,  // Configure the kernel backlog size
                'so_reuseport' => true,  // Allow reconnection to a recently closed port
            ],
        ]);
        // Create a TCP/IP socket
        $server = stream_socket_server(
            "tcp://0.0.0.0:8080",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $ctx
        );

        if (!$server) {
            die("Server creation failed: $errstr ($errno)");
        }

        stream_set_blocking($server, false);

        echo "Listening on port 8080...\n";

        $this->set('server', $server);
    },

    'server.starting.accept' => function (object $trigger) {
        $server = $this->get('server');
        $clients = [];
        $buffers = [];

        // Set the server socket to non-blocking mode
        echo "Accepting connections...\n";
        yield;
        while (true) {
            $read = [$server];
            $write = [];
            $except = [];
            foreach ($clients as $client) {
                $read[] = $client;
            }
            // Use stream_select for non-blocking I/O multiplexing
            // Timeout of 1 second to prevent blocking indefinitely
            $ready = stream_select($read, $write, $except, 0);

            if ($ready === false) {
                throw new Exception("stream_select failed");
            }
            if ($ready > 0) {
                // Check for new connections
                if (in_array($server, $read)) {
                    $client = stream_socket_accept($server, 0);
                    if ($client) {
                        // Make client socket non-blocking
                        stream_set_blocking($client, false);

                        // Add to clients array
                        $clientId = (int)$client;
                        $clients[$clientId] = $client;
                        $buffers[$clientId] = '';

                        $clientAddress = stream_socket_get_name($client, true);
                        echo "[".date('Y-m-d H:i:s')."] New connection from $clientAddress\n";
                    }
                    // Remove server socket from read array
                    $key = array_search($server, $read);
                    unset($read[$key]);
                }

                // Handle existing client connections
                foreach ($read as $client) {
                    $clientId = (int)$client;
                    // Read data from client (non-blocking)
                    $data = fread($client, 4096);

                    if ($data === false || $data === '') {
                        if (isset($clients[$clientId])) {
                            fclose($clients[$clientId]);
                            unset($clients[$clientId]);
                            unset($buffers[$clientId]);

                            echo "[".date('Y-m-d H:i:s')."] Connection closed\n";
                        }
                        continue;
                    }

                    // Append to buffer
                    $buffers[$clientId] .= $data;

                    // Check if we have a complete HTTP request
                    if (strpos($buffers[$clientId], "\r\n\r\n") !== false) {
                        $this->dispatch(new ServerConnection($client,$buffers[$clientId]));
                    }
                }
            }
            yield;
        }
    },
    'request.action.accept' => function (ServerConnection $connection) {
        //if ($connection->path !== '/favicon.ico') {
        //    return;
        //}
        // Handle the accepted connection here
        echo "[" . date('Y-m-d H:i:s') . "] $connection->method $connection->uri\n";
        $client = $connection->client;
        // Read a large chunk of data from the client
        $buffer = fread($client, 65536);
        if ($buffer === false || $buffer === '') {
            fclose($client);

            return;
        }

        // Split the request into headers and body (if any)
        $parts = explode("\r\n\r\n", $buffer, 2);
        $head = $parts[0];
        $body = $parts[1] ?? '';

        // Split the head into individual lines
        $headerLines = explode("\r\n", $head);
        $headers = [];
        foreach ($headerLines as $headerLine) {
            if (strpos($headerLine, ':') !== false) {
                [$key, $value] = explode(':', $headerLine, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        $regionId = spl_object_id($this);
        $resourceId = get_resource_id($client);
        $this->set('client', $client);
        $this->set('headers', $headers);
        $this->set('body', $body);
        $this->set('uri', $connection->uri);

        // Display the received HTTP request
        foreach ($headers as $name => $value) {
            //echo "  $name: $value\n";
        }
        echo $body.PHP_EOL;
    },
    'request.action.processing' => function (object $trigger) {
        $client = $this->get('client');
        $headers = $this->get('headers');
        //var_dump($headers);
        $regionId = spl_object_id($this);
        $resourceId = get_resource_id($client);
        //echo "Processing request $id: {$headers['Referer']}\n";

        if (!is_resource($client) || feof($client)) {
            return;
        }
        //var_dump($client);
        $response = "HTTP/1.1 200 OK\r\n"
            ."Connection: close\r\n"
            ."Content-Type: text/html\r\n"
            ."Date: ".gmdate('r')."\r\n"
            ."\r\n";
        $this->set('resourceId', get_resource_id($client));
        @fwrite($client, $response);
        $templateString = $this->get('bodyTemplate');
        $template = $this->template($templateString);
        assert($template instanceof \Generator);
        $result = '';
        while ($template->valid()) {
            $chunk = $template->current();
            if (!is_resource($client) || feof($client)) {
                break;
            }
            @fwrite($client, $chunk);

            //echo $chunk;
            $result .= $chunk;

            $template->next();
            yield;
        }
        echo "Template rendering finished. Closing connection\n";
        $this->set('close', true);
    },
    'request.onEnter.close' => function (object $trigger) {
        $client = $this->get('client');
        $path = $this->get('path');
        $regionId = spl_object_id($this);
        $resourceId = get_resource_id($client);
        echo "Closing request $resourceId|$regionId: $path\n";
        fclose($client);
    },
    'transition.guard.processing.close' => function (object $trigger): bool {
        $close = $this->get('close');

        return $close === true;
    },
];

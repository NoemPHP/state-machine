<?php

declare(strict_types=1);

return [
    'request.action.accept' => function (ServerConnection $connection) {
        // Handle the accepted connection here
        echo "Connection accepted\n";
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

        $this->set('client', $client);
        $this->set('headers', $headers);
        $this->set('body', $body);

        // Display the received HTTP request
        foreach ($headers as $name => $value) {
            //echo "  $name: $value\n";
        }
        echo $body.PHP_EOL;
    },
    'request.action.processing' => function (object $trigger) {
        $client = $this->get('client');
        $headers = $this->get('headers');
        var_dump($headers);
        $id = get_resource_id($client);
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
        $template = $this->template($this->get('bodyTemplate'));
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
        $id = get_resource_id($client);
        echo "Closing request $id\n";
        fclose($client);
    },
];

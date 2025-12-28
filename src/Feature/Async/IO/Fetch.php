<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\IO;

readonly class Fetch
{
    public function __construct(
        private string $url,
        private string $method = 'GET',
        private array $headers = [],
        private string $body = '',
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): \Generator
    {
        $headerString = $this->compileHeaders();
        $headerString .= "\r\nContent-Length: " . strlen($this->body);
        $context = stream_context_create([
            'http' => [
                'method' => $this->method,
                'header' => $headerString,
                'content' => $this->body,
                'timeout' => 300, // 5 minutes for large AI requests
                'ignore_errors' => false,
            ],
        ]);

        $resource = fopen($this->url, 'r', false, $context);
        if ($resource === false) {
            throw new \Exception('Failed to open stream');
        }

        $generator = new StreamHandler($resource)();
        yield from $generator;

        return $generator->getReturn();
    }

    private function compileHeaders(): string
    {
        $headerLines = [];
        foreach ($this->headers as $header => $value) {
            $headerLines[] = "$header: $value";
        }

        return implode("\r\n", $headerLines);
    }
}

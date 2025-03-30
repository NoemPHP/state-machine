<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\State\Feature\Async\IO\Fetch;

class Completion
{
    private Request $request;

    public function __construct(
        string|Request $request,
        private readonly ?bool $asText = true
    ) {
        if (is_scalar($request)) {
            $request = new RequestBuilder()->setPrompt($request)->build();
        }
        $this->request = $request;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): \Generator
    {
        $args = [
            'model' => $this->request->model,
            'prompt' => $this->request->prompt,
            'stream' => $this->request->stream,
        ];

        if ($this->request->responseFormat) {
            $args['response_format'] = [
                'type' => $this->request->responseFormat->format,
                $this->request->responseFormat->format => $this->request->responseFormat->definition,
            ];
        }
        $fetch = new Fetch(
            "{$this->request->baseUrl}/completions",
            'POST',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->request->token,
            ],
            json_encode($args)
        )();
        $buffer = '';
        while ($fetch->valid()) {
            $chunk = $fetch->current();
            $fetch->next();
            $buffer .= $chunk;

            $output = $this->processBuffer($buffer);

            while ($output->valid()) {
                $payload = $output->current();
                yield $payload;
                $output->next();
            }
            $buffer = $output->getReturn();
        }
        $buffer .= $fetch->current();
        /**
         * If there is data remaining in the buffer
         */
        yield from $this->processBuffer($buffer);
    }

    private function processBuffer(string $buffer): \Generator
    {
        // Split the buffer into lines
        $lines = explode("\n", $buffer);
        foreach (array_slice($lines, 0, -1) as $line) {
            if (strpos($line, 'data: ') === 0) {
                $payload = substr($line, strlen('data: '));
                if ($payload !== '') {
                    $decoded = json_decode($payload, true);
                    if (!$decoded) {
                        continue;
                    }
                    if ($this->asText) {
                        yield $decoded['choices'][0]['text'];
                        continue;
                    }
                    yield $decoded;
                }
            }
        }

        // Keep the last incomplete line in the buffer
        return end($lines);
    }
}

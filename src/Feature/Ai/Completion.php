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
            'stop' => $this->request->stop,
            'max_tokens' => $this->request->maxTokens,
            'temperature' => $this->request->temperature,
            'suffix' => '',
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
                'Authorization' => 'Bearer '.$this->request->token,
            ],
            json_encode($args)
        )();
        $jsonChunks = $this->processApiResponse($fetch, $this->request->stream);
        if ($this->asText) {
            yield from $this->extractText($jsonChunks, $this->request->stop);

            return;
        }
        yield from $jsonChunks;
    }

    public function extractText(\Generator $response, ?string $stopSeq = null): \Generator
    {
        $buffer = '';

        while ($response->valid()) {
            $payload = $response->current();
            $text = $payload['choices'][0]['text'];
            $buffer .= $text;

            if ($stopSeq !== null) {
                $pos = strpos($buffer, $stopSeq);
                if ($pos !== false) {
                    // Found stop sequence, yield only up to it
                    if ($pos > 0) {
                        yield substr($buffer, 0, $pos);
                    }
                    break; // Stop iterating after stop sequence
                }

                // If stopSeq not in buffer and it's reasonably small, yield current and clear buffer
                if (strlen($buffer) >= strlen($stopSeq)) {
                    // Yield everything except the last N-1 characters in case the stop sequence starts there
                    $safeLen = strlen($buffer) - strlen($stopSeq) + 1;
                    yield substr($buffer, 0, $safeLen);
                    $buffer = substr($buffer, $safeLen);
                }
            } else {
                // No stop sequence: just yield directly
                yield $text;
            }

            $response->next();
        }

        // Yield anything left in buffer if no stop sequence was found
        if ($stopSeq !== null && $buffer !== '') {
            yield $buffer;
        }
    }

    public function processApiResponse(\Generator $response, bool $streaming): \Generator
    {
        $buffer = '';
        while ($response->valid()) {
            $chunk = $response->current();
            $response->next();
            $buffer .= $chunk;

            $output = $this->processBuffer($buffer, $streaming);

            while ($output->valid()) {
                $payload = $output->current();
                yield $payload;
                $output->next();
            }
            $buffer = $output->getReturn();
        }
        $buffer .= $response->current();
        /**
         * If there is data remaining in the buffer
         */
        yield from $this->processBuffer($buffer, $streaming);
    }

    private function processBuffer(string $buffer, bool $streaming): \Generator
    {
        // Split the buffer into lines
        $lines = explode("\n", $buffer);
        foreach (array_slice($lines, 0, -1) as $line) {
            if ($streaming && strpos($line, 'data: ') === 0) {
                $line = substr($line, strlen('data: '));
            }
            if ($line !== '') {
                $decoded = json_decode($line, true);
                if (!$decoded) {
                    continue;
                }
                yield $decoded;
            }
        }

        // Keep the last incomplete line in the buffer
        return end($lines);
    }
}

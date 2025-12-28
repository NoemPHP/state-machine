<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\State\Feature\Ai\Backend\BackendInterface;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\Async\IO\Fetch;

class Completion
{
    private Request $request;
    private BackendInterface $backend;

    public function __construct(
        string|Request $request,
        private readonly ?bool $asText = true,
        ?BackendInterface $backend = null
    ) {
        if (is_scalar($request)) {
            $request = new RequestBuilder()->setPrompt($request)->build();
        }
        $this->request = $request;
        $this->backend = $backend ?? new OpenAiBackend();
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): \Generator
    {
        // Use backend to stream responses
        $jsonChunks = $this->backend->stream($this->request);

        if ($this->asText) {
            yield from $this->extractText($jsonChunks, $this->request->stop);

            return;
        }
        yield from $jsonChunks;
    }

    public function extractText(iterable $response, ?string $stopSeq = null): \Generator
    {
        $buffer = '';

        foreach ($response as $payload) {
            $text = $payload['choices'][0]['text'] ?? '';
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
        }

        // Yield anything left in buffer if no stop sequence was found
        if ($stopSeq !== null && $buffer !== '') {
            yield $buffer;
        }
    }
}

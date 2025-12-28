<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\State\Feature\Ai\Backend\BackendInterface;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\Async\IO\Fetch;

class Chat
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
        $responses = $this->backend->stream($this->request);

        foreach ($responses as $payload) {
            if ($this->asText) {
                yield $payload['choices'][0]['message']['content'] ?? '';
                continue;
            }
            yield $payload;
        }
    }
}

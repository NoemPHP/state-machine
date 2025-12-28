<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Ollama;

use Noem\State\Feature\Ai\Backend\OllamaBackend;
use Noem\State\Feature\Ai\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OllamaBackend stream() yields text chunks from Ollama streaming API
 *
 * Intent: Handles Ollama streaming format, parsing JSON lines and extracting
 * response content incrementally
 */
#[Group('ai'), Group('ollama-backend-implementation')]
class StreamsResponseChunksTest extends TestCase
{
    #[Test]
    public function streamMethodReturnsIterable(): void
    {
        $backend = new OllamaBackend();

        $request = new Request(
            baseUrl: 'http://localhost:11434',
            token: '',
            model: 'llama2',
            prompt: 'Test prompt',
            maxTokens: 100,
            temperature: 0.7,
            stop: null,
            responseFormat: null,
            logprobs: true,
            stream: true
        );

        $result = $backend->stream($request);

        // Verify it returns an iterable (generator)
        $this->assertIsIterable($result);
    }
}

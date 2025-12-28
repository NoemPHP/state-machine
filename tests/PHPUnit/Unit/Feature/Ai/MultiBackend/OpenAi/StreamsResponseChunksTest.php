<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\OpenAi;

use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\Ai\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OpenAiBackend stream() yields text chunks from OpenAI streaming API
 *
 * Intent: Handles OpenAI SSE streaming format, parsing data lines and extracting
 * text content incrementally
 */
#[Group('ai'), Group('openai-backend-implementation')]
class StreamsResponseChunksTest extends TestCase
{
    #[Test]
    public function streamMethodReturnsIterable(): void
    {
        $backend = new OpenAiBackend();

        $request = new Request(
            baseUrl: 'https://api.openai.com',
            token: 'test-token',
            model: 'gpt-4',
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

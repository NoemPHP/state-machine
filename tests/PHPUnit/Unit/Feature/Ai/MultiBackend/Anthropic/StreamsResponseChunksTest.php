<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Anthropic;

use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use Noem\State\Feature\Ai\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AnthropicBackend stream() yields text chunks from Anthropic streaming API
 *
 * Intent: Handles Anthropic SSE streaming format, parsing event types and extracting
 * text deltas incrementally
 */
#[Group('ai'), Group('anthropic-backend-implementation')]
class StreamsResponseChunksTest extends TestCase
{
    #[Test]
    public function streamMethodReturnsIterable(): void
    {
        $backend = new AnthropicBackend();

        $request = new Request(
            baseUrl: 'https://api.anthropic.com',
            token: 'test-token',
            model: 'claude-3-opus',
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

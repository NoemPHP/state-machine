<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Anthropic;

use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use Noem\State\Feature\Ai\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AnthropicBackend createCompletionRequest() transforms Request to Anthropic completion API format
 *
 * Intent: Converts generic Request object to Anthropic-specific completion request structure
 * with proper field mapping
 */
#[Group('ai'), Group('anthropic-backend-implementation')]
class TransformsCompletionRequestTest extends TestCase
{
    #[Test]
    public function transformsRequestToAnthropicCompletionFormat(): void
    {
        $backend = new AnthropicBackend();

        $request = new Request(
            baseUrl: 'https://api.anthropic.com',
            token: 'test-token',
            model: 'claude-3-opus',
            prompt: 'Test prompt',
            maxTokens: 100,
            temperature: 0.7,
            stop: 'STOP',
            responseFormat: null,
            logprobs: true,
            stream: true
        );

        $result = $backend->createCompletionRequest($request);

        $this->assertIsArray($result);
        $this->assertEquals('claude-3-opus', $result['model']);
        $this->assertEquals(100, $result['max_tokens']);
        $this->assertEquals(0.7, $result['temperature']);
        $this->assertTrue($result['stream']);

        // Anthropic uses messages array even for completion
        $this->assertArrayHasKey('messages', $result);
        $this->assertIsArray($result['messages']);
        $this->assertCount(1, $result['messages']);
        $this->assertEquals('user', $result['messages'][0]['role']);
        $this->assertEquals('Test prompt', $result['messages'][0]['content']);
    }
}

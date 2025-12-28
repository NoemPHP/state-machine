<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Anthropic;

use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use Noem\State\Feature\Ai\Request;
use Noem\State\Feature\Ai\ResponseFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AnthropicBackend createChatRequest() transforms Request to Anthropic messages API format
 *
 * Intent: Converts generic Request object to Anthropic-specific messages request structure
 * with system parameter and messages array
 */
#[Group('ai'), Group('anthropic-backend-implementation')]
class TransformsChatRequestTest extends TestCase
{
    #[Test]
    public function transformsRequestToAnthropicMessagesFormat(): void
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

        $result = $backend->createChatRequest($request);

        $this->assertIsArray($result);
        $this->assertEquals('claude-3-opus', $result['model']);
        $this->assertEquals(100, $result['max_tokens']);
        $this->assertEquals(0.7, $result['temperature']);
        $this->assertTrue($result['stream']);

        // Anthropic uses separate system parameter
        $this->assertArrayHasKey('system', $result);
        $this->assertEquals('You are a helpful assistant', $result['system']);

        // Verify messages array
        $this->assertArrayHasKey('messages', $result);
        $this->assertIsArray($result['messages']);
        $this->assertCount(1, $result['messages']);
        $this->assertEquals('user', $result['messages'][0]['role']);
        $this->assertEquals('Test prompt', $result['messages'][0]['content']);
    }

    #[Test]
    public function appendsJsonInstructionToSystemPromptWhenResponseFormatProvided(): void
    {
        $backend = new AnthropicBackend();

        $responseFormat = new ResponseFormat(
            'json_schema',
            ['type' => 'object', 'properties' => ['test' => ['type' => 'string']]]
        );

        $request = new Request(
            baseUrl: 'https://api.anthropic.com',
            token: 'test-token',
            model: 'claude-3-opus',
            prompt: 'Test prompt',
            maxTokens: 100,
            temperature: 0.7,
            stop: null,
            responseFormat: $responseFormat,
            logprobs: true,
            stream: false
        );

        $result = $backend->createChatRequest($request);

        $this->assertArrayHasKey('system', $result);
        $this->assertStringContainsString('Respond only with valid JSON', $result['system']);
    }
}

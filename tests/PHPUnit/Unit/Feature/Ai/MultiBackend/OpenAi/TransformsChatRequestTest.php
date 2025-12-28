<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\OpenAi;

use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\Ai\Request;
use Noem\State\Feature\Ai\ResponseFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OpenAiBackend createChatRequest() transforms Request to OpenAI chat API format
 *
 * Intent: Converts generic Request object to OpenAI-specific chat request structure
 * with messages array and roles
 */
#[Group('ai'), Group('openai-backend-implementation')]
class TransformsChatRequestTest extends TestCase
{
    #[Test]
    public function transformsRequestToOpenAiChatFormat(): void
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

        $result = $backend->createChatRequest($request);

        $this->assertIsArray($result);
        $this->assertEquals('gpt-4', $result['model']);
        $this->assertTrue($result['stream']);
        $this->assertArrayHasKey('messages', $result);
        $this->assertIsArray($result['messages']);
        $this->assertCount(2, $result['messages']);

        // Verify developer message
        $this->assertEquals('developer', $result['messages'][0]['role']);
        $this->assertEquals('You are a helpful assistant', $result['messages'][0]['content']);

        // Verify user message
        $this->assertEquals('user', $result['messages'][1]['role']);
        $this->assertEquals('Test prompt', $result['messages'][1]['content']);
    }

    #[Test]
    public function includesResponseFormatWhenProvided(): void
    {
        $backend = new OpenAiBackend();

        $responseFormat = new ResponseFormat(
            'json_schema',
            ['type' => 'object', 'properties' => ['test' => ['type' => 'string']]]
        );

        $request = new Request(
            baseUrl: 'https://api.openai.com',
            token: 'test-token',
            model: 'gpt-4',
            prompt: 'Test prompt',
            maxTokens: 100,
            temperature: 0.7,
            stop: null,
            responseFormat: $responseFormat,
            logprobs: true,
            stream: false
        );

        $result = $backend->createChatRequest($request);

        $this->assertArrayHasKey('response_format', $result);
        $this->assertEquals('json_schema', $result['response_format']['type']);
        $this->assertArrayHasKey('json_schema', $result['response_format']);
    }
}

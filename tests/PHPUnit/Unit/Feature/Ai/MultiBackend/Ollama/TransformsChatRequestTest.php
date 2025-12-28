<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Ollama;

use Noem\State\Feature\Ai\Backend\OllamaBackend;
use Noem\State\Feature\Ai\Request;
use Noem\State\Feature\Ai\ResponseFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OllamaBackend createChatRequest() transforms Request to Ollama chat API format
 *
 * Intent: Converts generic Request object to Ollama-specific chat request structure
 * with messages array
 */
#[Group('ai'), Group('ollama-backend-implementation')]
class TransformsChatRequestTest extends TestCase
{
    #[Test]
    public function transformsRequestToOllamaChatFormat(): void
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

        $result = $backend->createChatRequest($request);

        $this->assertIsArray($result);
        $this->assertEquals('llama2', $result['model']);
        $this->assertTrue($result['stream']);
        $this->assertArrayHasKey('messages', $result);
        $this->assertIsArray($result['messages']);
        $this->assertCount(2, $result['messages']);

        // Verify system message
        $this->assertEquals('system', $result['messages'][0]['role']);
        $this->assertEquals('You are a helpful assistant', $result['messages'][0]['content']);

        // Verify user message
        $this->assertEquals('user', $result['messages'][1]['role']);
        $this->assertEquals('Test prompt', $result['messages'][1]['content']);

        // Verify options
        $this->assertArrayHasKey('options', $result);
        $this->assertEquals(0.7, $result['options']['temperature']);
        $this->assertEquals(100, $result['options']['num_predict']);
    }

    #[Test]
    public function includesFormatJsonWhenResponseFormatProvided(): void
    {
        $backend = new OllamaBackend();

        $responseFormat = new ResponseFormat(
            'json_schema',
            ['type' => 'object', 'properties' => ['test' => ['type' => 'string']]]
        );

        $request = new Request(
            baseUrl: 'http://localhost:11434',
            token: '',
            model: 'llama2',
            prompt: 'Test prompt',
            maxTokens: 100,
            temperature: 0.7,
            stop: null,
            responseFormat: $responseFormat,
            logprobs: true,
            stream: false
        );

        $result = $backend->createChatRequest($request);

        $this->assertArrayHasKey('format', $result);
        $this->assertEquals('json', $result['format']);
    }
}

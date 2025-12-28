<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Ollama;

use Noem\State\Feature\Ai\Backend\OllamaBackend;
use Noem\State\Feature\Ai\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OllamaBackend createCompletionRequest() transforms Request to Ollama completion API format
 *
 * Intent: Converts generic Request object to Ollama-specific completion request structure
 * with proper field mapping
 */
#[Group('ai'), Group('ollama-backend-implementation')]
class TransformsCompletionRequestTest extends TestCase
{
    #[Test]
    public function transformsRequestToOllamaCompletionFormat(): void
    {
        $backend = new OllamaBackend();

        $request = new Request(
            baseUrl: 'http://localhost:11434',
            token: '',
            model: 'llama2',
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
        $this->assertEquals('llama2', $result['model']);
        $this->assertEquals('Test prompt', $result['prompt']);
        $this->assertTrue($result['stream']);
        $this->assertArrayHasKey('options', $result);
        $this->assertEquals(0.7, $result['options']['temperature']);
        $this->assertEquals(100, $result['options']['num_predict']);
        $this->assertEquals(['STOP'], $result['options']['stop']);
    }
}

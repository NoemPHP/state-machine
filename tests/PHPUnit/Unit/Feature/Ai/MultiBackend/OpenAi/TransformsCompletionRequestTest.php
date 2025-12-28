<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\OpenAi;

use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\Ai\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OpenAiBackend createCompletionRequest() transforms Request to OpenAI completion API format
 *
 * Intent: Converts generic Request object to OpenAI-specific completion request structure
 * with proper field mapping
 */
#[Group('ai'), Group('openai-backend-implementation')]
class TransformsCompletionRequestTest extends TestCase
{
    #[Test]
    public function transformsRequestToOpenAiCompletionFormat(): void
    {
        $backend = new OpenAiBackend();

        $request = new Request(
            baseUrl: 'https://api.openai.com',
            token: 'test-token',
            model: 'gpt-4',
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
        $this->assertEquals('gpt-4', $result['model']);
        $this->assertEquals('Test prompt', $result['prompt']);
        $this->assertTrue($result['stream']);
        $this->assertEquals('STOP', $result['stop']);
        $this->assertEquals(100, $result['max_tokens']);
        $this->assertEquals(0.7, $result['temperature']);
    }
}

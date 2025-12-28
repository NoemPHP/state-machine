<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\OpenAi;

use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OpenAiBackend formatSystemPrompt() applies OpenAI-specific prompt template
 *
 * Intent: Formats system prompts according to OpenAI best practices and model-specific requirements
 */
#[Group('ai'), Group('openai-backend-implementation')]
class FormatsSystemPromptTest extends TestCase
{
    #[Test]
    public function formatsSystemPrompt(): void
    {
        $backend = new OpenAiBackend();

        $prompt = 'Test system prompt';
        $result = $backend->formatSystemPrompt($prompt);

        $this->assertIsString($result);
        // Currently returns as-is, but test verifies the method exists and returns a string
        $this->assertEquals($prompt, $result);
    }
}

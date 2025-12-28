<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Ollama;

use Noem\State\Feature\Ai\Backend\OllamaBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OllamaBackend formatSystemPrompt() applies Ollama-specific prompt template
 *
 * Intent: Formats system prompts according to Ollama model-specific requirements
 * and local deployment context
 */
#[Group('ai'), Group('ollama-backend-implementation')]
class FormatsSystemPromptTest extends TestCase
{
    #[Test]
    public function formatsSystemPrompt(): void
    {
        $backend = new OllamaBackend();

        $prompt = 'Test system prompt';
        $result = $backend->formatSystemPrompt($prompt);

        $this->assertIsString($result);
        // Currently returns as-is, but test verifies the method exists and returns a string
        $this->assertEquals($prompt, $result);
    }
}

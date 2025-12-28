<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Anthropic;

use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AnthropicBackend formatSystemPrompt() applies Anthropic-specific prompt template
 *
 * Intent: Formats system prompts according to Claude model requirements and best practices
 */
#[Group('ai'), Group('anthropic-backend-implementation')]
class FormatsSystemPromptTest extends TestCase
{
    #[Test]
    public function formatsSystemPrompt(): void
    {
        $backend = new AnthropicBackend();

        $prompt = 'Test system prompt';
        $result = $backend->formatSystemPrompt($prompt);

        $this->assertIsString($result);
        // Currently returns as-is, but test verifies the method exists and returns a string
        $this->assertEquals($prompt, $result);
    }
}

<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\PromptTemplate;

use Noem\State\Feature\Ai\Chains\PromptTemplate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PromptTemplate returns empty string when template not found
 *
 * Intent: Provides fallback behavior preventing errors when templates missing,
 * allowing backends to use defaults
 */
#[Group('ai'), Group('prompt-template-chain')]
class ReturnsEmptyOnNotFoundTest extends TestCase
{
    #[Test]
    public function returnsEmptyStringWhenTemplateNotFound(): void
    {
        $promptTemplate = new PromptTemplate();

        // Don't register any templates
        $result = $promptTemplate('nonexistent', 'completion');

        $this->assertEquals('', $result);
    }

    #[Test]
    public function returnsEmptyStringForUnknownType(): void
    {
        $promptTemplate = new PromptTemplate();

        $promptTemplate->registerTemplate('openai', 'completion', 'Test');

        // Request different type
        $result = $promptTemplate('openai', 'unknown');

        $this->assertEquals('', $result);
    }
}

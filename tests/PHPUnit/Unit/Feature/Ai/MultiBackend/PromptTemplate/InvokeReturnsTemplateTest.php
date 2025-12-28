<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\PromptTemplate;

use Noem\State\Feature\Ai\Chains\PromptTemplate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PromptTemplate __invoke(backend, type) returns string template
 *
 * Intent: Defines invocation signature accepting backend name and template type,
 * returning resolved template string
 */
#[Group('ai'), Group('prompt-template-chain')]
class InvokeReturnsTemplateTest extends TestCase
{
    #[Test]
    public function invokeReturnsStringTemplate(): void
    {
        $promptTemplate = new PromptTemplate();

        $result = $promptTemplate('openai', 'completion');

        $this->assertIsString($result);
    }

    #[Test]
    public function returnsRegisteredTemplate(): void
    {
        $promptTemplate = new PromptTemplate();
        $promptTemplate->registerTemplate('openai', 'completion', 'Test template');

        $result = $promptTemplate('openai', 'completion');

        $this->assertEquals('Test template', $result);
    }
}

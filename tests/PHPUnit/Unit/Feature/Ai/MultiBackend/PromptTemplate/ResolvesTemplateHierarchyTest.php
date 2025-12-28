<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\PromptTemplate;

use Noem\State\Feature\Ai\Chains\PromptTemplate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PromptTemplate resolves templates in order - model → provider → base
 *
 * Intent: Implements resolution hierarchy enabling progressive template specialization
 * from general to specific
 */
#[Group('ai'), Group('prompt-template-chain')]
class ResolvesTemplateHierarchyTest extends TestCase
{
    #[Test]
    public function providerTemplateOverridesBaseTemplate(): void
    {
        $promptTemplate = new PromptTemplate();

        $promptTemplate->registerBaseTemplate('completion', 'Base template');
        $promptTemplate->registerTemplate('openai', 'completion', 'OpenAI template');

        $result = $promptTemplate('openai', 'completion');

        $this->assertEquals('OpenAI template', $result);
    }

    #[Test]
    public function fallsBackToBaseTemplateWhenNoProviderTemplate(): void
    {
        $promptTemplate = new PromptTemplate();

        $promptTemplate->registerBaseTemplate('completion', 'Base template');

        $result = $promptTemplate('openai', 'completion');

        $this->assertEquals('Base template', $result);
    }

    #[Test]
    public function returnsEmptyStringWhenNoTemplatesRegistered(): void
    {
        $promptTemplate = new PromptTemplate();

        $result = $promptTemplate('openai', 'completion');

        $this->assertEquals('', $result);
    }
}

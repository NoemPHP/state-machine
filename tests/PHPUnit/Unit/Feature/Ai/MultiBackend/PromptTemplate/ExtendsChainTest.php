<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\PromptTemplate;

use Noem\State\Feature\Ai\Chains\PromptTemplate;
use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PromptTemplate extends Chain class
 *
 * Intent: Leverages Chain infrastructure for middleware-based template resolution extensibility
 */
#[Group('ai'), Group('prompt-template-chain')]
class ExtendsChainTest extends TestCase
{
    #[Test]
    public function extendsChainClass(): void
    {
        $promptTemplate = new PromptTemplate();

        $this->assertInstanceOf(Chain::class, $promptTemplate);
    }
}

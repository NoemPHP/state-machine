<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\Chains\PromptTemplate;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AiFeature supplies PromptTemplate chain via ChainMail
 *
 * Intent: Provides prompt template resolution through ChainMail dependency injection,
 * enabling provider-specific prompt formatting
 */
#[Group('ai'), Group('prompt-template-chain')]
class SuppliesPromptTemplateChainTest extends TestCase
{
    #[Test]
    public function suppliesPromptTemplateChainViaChainMail(): void
    {
        $chainMail = new ChainMail();

        $feature = new AiFeature();
        $feature($chainMail);

        $promptTemplate = $chainMail->get(PromptTemplate::class);
        $this->assertInstanceOf(PromptTemplate::class, $promptTemplate);
    }
}

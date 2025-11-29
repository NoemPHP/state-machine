<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AiFeature registers SystemPrompt chain in ChainMail
 */
#[Group('ai'), Group('feature-registration')]
class RegistersSystemPromptChainTest extends TestCase
{
    #[Test]
    public function registersSystemPromptChain(): void
    {
        $chainMail = new \Noem\State\Middleware\ChainMail();

        $feature = new \Noem\State\Feature\Ai\AiFeature();
        $feature($chainMail);

        $systemPrompt = $chainMail->get(\Noem\State\Feature\Ai\Chains\SystemPrompt::class);
        $this->assertInstanceOf(\Noem\State\Feature\Ai\Chains\SystemPrompt::class, $systemPrompt);
    }
}

<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Registration;

use Noem\State\Feature\Agentic\AgenticFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AgenticFeature registers weave() method in ExtendedState context
 *
 * Criticality: contract
 * Intent: Provides agentic operation capabilities through context API, enabling AI-powered
 * tool selection and execution from state actions
 *
 * @spec /specs/features/agentic.yaml:19-22
 */
#[Group('ai'), Group('weave'), Group('registration')]
final class RegistersWeaveMethodTest extends TestCase
{
    #[Test]
    public function aiFeatureRegistersWeaveMethod(): void
    {
        $chainMail = new ChainMail();

        // First register required dependencies
        $chainMail->supply(fn(): \Noem\State\Feature\Abilities\Chains\InvokeAbility => $this->createMock(\Noem\State\Feature\Abilities\Chains\InvokeAbility::class));
        $chainMail->supply(fn(): \Noem\State\Middleware\Mesh => new \Noem\State\Middleware\Mesh());
        $chainMail->supply(fn(): \Noem\State\Feature\Abilities\AbilityRegistry => $this->createMock(\Noem\State\Feature\Abilities\AbilityRegistry::class));

        $feature = new AgenticFeature();
        $feature($chainMail);

        // Verify Weave chain is registered in ChainMail
        $weaveChain = $chainMail->get(\Noem\State\Feature\Agentic\Chains\Weave::class);

        $this->assertInstanceOf(
            \Noem\State\Feature\Agentic\Chains\Weave::class,
            $weaveChain,
            'AgenticFeature should register Weave chain in ChainMail'
        );
    }
}

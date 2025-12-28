<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() returns Generator yielding associative array result
 *
 * Criticality: contract
 * Intent: Provides async-compatible result delivery through generator, yielding during
 * AI calls and tool execution
 *
 * @spec /specs/features/agentic.yaml:72-75
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class ReturnsGeneratorTest extends TestCase
{
    #[Test]
    public function weaveReturnsGenerator(): void
    {
        // Set up mocks
        $chainMail = new \Noem\State\Middleware\ChainMail();

        $invokeAbility = $this->createMock(\Noem\State\Feature\Abilities\Chains\InvokeAbility::class);
        $aiBackends = new \Noem\State\Middleware\Mesh();
        $registry = $this->createMock(\Noem\State\Feature\Abilities\AbilityRegistry::class);

        $chainMail->supply(fn(): \Noem\State\Feature\Abilities\Chains\InvokeAbility => $invokeAbility);
        $chainMail->supply(fn(): \Noem\State\Middleware\Mesh => $aiBackends);
        $chainMail->supply(fn(): \Noem\State\Feature\Abilities\AbilityRegistry => $registry);

        $feature = new \Noem\State\Feature\Agentic\AgenticFeature();
        $feature($chainMail);

        $weaveChain = $chainMail->get(\Noem\State\Feature\Agentic\Chains\Weave::class);

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $this->createMock(\Noem\State\Region::class),
            intent: 'test intent'
        );

        $result = $weaveChain->call($params);

        $this->assertInstanceOf(\Generator::class, $result, 'Weave chain should return a Generator');
    }
}

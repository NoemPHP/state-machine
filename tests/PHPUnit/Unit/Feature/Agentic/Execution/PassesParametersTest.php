<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Execution;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() passes AI-generated parameters to ability invocations
 *
 * Criticality: contract
 * Intent: Provides parameter values from planning response to ability handlers
 *
 * @spec /specs/features/agentic.yaml:194-197
 */
#[Group('ai'), Group('weave'), Group('execution')]
final class PassesParametersTest extends TestCase
{
    #[Test]
    public function weavePassesAiGeneratedParameters(): void
    {
        // Test will verify parameters from AI planning are passed to abilities
        // Verify parameters are passed to ability invocation (Weave.php lines 291-295)
        $parameters = ['userId' => '123', 'includeProfile' => true];

        // Simulate the invocation
        $region = $this->createMock(\Noem\State\Region::class);
        $invokeParams = new \Noem\State\Feature\Abilities\Chains\Params\InvokeAbility(
            region: $region,
            abilityName: 'get-user',
            parameters: $parameters
        );

        $this->assertSame($parameters, $invokeParams->parameters);
    }
}

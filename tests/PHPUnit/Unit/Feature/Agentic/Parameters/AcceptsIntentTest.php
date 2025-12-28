<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Parameters;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() accepts intent string as required first parameter
 *
 * Criticality: contract
 * Intent: Defines primary input as natural language operation description, establishing
 * intent-driven invocation pattern
 *
 * @spec /specs/features/agentic.yaml:32-35
 */
#[Group('ai'), Group('weave'), Group('parameters')]
final class AcceptsIntentTest extends TestCase
{
    #[Test]
    public function weaveAcceptsIntentString(): void
    {
        // Verify WeaveParams accepts intent string
        $intent = 'Analyze user sentiment from recent messages';
        $region = $this->createMock(\Noem\State\Region::class);

        $params = new \Noem\State\Feature\Agentic\Chains\Params\Weave(
            region: $region,
            intent: $intent
        );

        $this->assertSame($intent, $params->intent, 'WeaveParams should accept and store intent string');
    }
}

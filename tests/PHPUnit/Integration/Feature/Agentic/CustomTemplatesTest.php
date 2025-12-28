<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Agentic;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() with custom AiConfigFeature templates uses configured prompts
 *
 * Criticality: integration
 * Intent: Validates custom prompt template application through configuration
 *
 * @spec /specs/features/agentic.yaml:529-532
 */
#[Group('integration'), Group('agentic'), Group('weave'), Group('ai')]
final class CustomTemplatesTest extends TestCase
{
    #[Test]
    public function weaveUsesCustomConfiguredTemplates(): void
    {
        // TODO: Implement integration test for custom templates
        // - Set up AiConfigFeature with custom prompt templates:
        //   - Custom planning template
        //   - Custom aggregation template
        //   - Custom continuation template
        // - Mock AI backend to capture prompts sent
        // - Execute weave() through full workflow (planning, execution, continuation, aggregation)
        // - Verify custom templates are used instead of defaults:
        //   - Planning prompt uses custom planning template
        //   - Aggregation prompt uses custom aggregation template
        //   - Continuation prompt uses custom continuation template
        // - Verify templates receive correct variable substitution

        $this->markTestIncomplete('Integration test requires custom template and AI backend mocking');
    }
}

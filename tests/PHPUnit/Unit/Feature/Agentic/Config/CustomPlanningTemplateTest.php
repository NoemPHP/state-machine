<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Config;

use Noem\State\Feature\Agentic\WeaveConfig;
use Noem\State\Feature\Ai\AiConfigFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AiConfigFeature weave.promptTemplates accepts custom planning template
 *
 * Criticality: contract
 * Intent: Enables custom planning prompt format through configuration
 *
 * @spec /specs/features/agentic.yaml:411-414
 */
#[Group('ai'), Group('weave'), Group('config')]
final class CustomPlanningTemplateTest extends TestCase
{
    #[Test]
    public function customPlanningTemplateIsConfigurable(): void
    {
        // Configure with custom planning template
        $aiConfig = new AiConfigFeature([
            'weave' => [
                'promptTemplates' => [
                    'planning' => 'Custom: Select tools for {intent}',
                ],
            ],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify custom template is stored
        $this->assertSame('Custom: Select tools for {intent}', $weaveConfig->getPlanningTemplate());
    }

    #[Test]
    public function planningTemplateCanBeNull(): void
    {
        // No templates configured
        $aiConfig = new AiConfigFeature([
            'weave' => [],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify null when not configured
        $this->assertNull($weaveConfig->getPlanningTemplate());
    }
}

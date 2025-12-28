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
 * Acceptance Criterion: AiConfigFeature weave.promptTemplates accepts custom continuation template
 *
 * Criticality: contract
 * Intent: Enables custom continuation prompt format for multi-iteration
 *
 * @spec /specs/features/agentic.yaml:421-424
 */
#[Group('ai'), Group('weave'), Group('config')]
final class CustomContinuationTemplateTest extends TestCase
{
    #[Test]
    public function customContinuationTemplateIsConfigurable(): void
    {
        // Configure with custom continuation template
        $aiConfig = new AiConfigFeature([
            'weave' => [
                'promptTemplates' => [
                    'continuation' => 'Continue with {previousResults}',
                ],
            ],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify custom template is stored
        $this->assertSame('Continue with {previousResults}', $weaveConfig->getContinuationTemplate());
    }

    #[Test]
    public function continuationTemplateCanBeNull(): void
    {
        // No templates configured
        $aiConfig = new AiConfigFeature([
            'weave' => [],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify null when not configured
        $this->assertNull($weaveConfig->getContinuationTemplate());
    }
}

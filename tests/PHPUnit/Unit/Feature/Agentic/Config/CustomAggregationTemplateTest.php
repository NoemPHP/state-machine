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
 * Acceptance Criterion: AiConfigFeature weave.promptTemplates accepts custom aggregation template
 *
 * Criticality: contract
 * Intent: Enables custom aggregation prompt format through configuration
 *
 * @spec /specs/features/agentic.yaml:416-419
 */
#[Group('ai'), Group('weave'), Group('config')]
final class CustomAggregationTemplateTest extends TestCase
{
    #[Test]
    public function customAggregationTemplateIsConfigurable(): void
    {
        // Configure with custom aggregation template
        $aiConfig = new AiConfigFeature([
            'weave' => [
                'promptTemplates' => [
                    'aggregation' => 'Synthesize results: {results}',
                ],
            ],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify custom template is stored
        $this->assertSame('Synthesize results: {results}', $weaveConfig->getAggregationTemplate());
    }

    #[Test]
    public function aggregationTemplateCanBeNull(): void
    {
        // No templates configured
        $aiConfig = new AiConfigFeature([
            'weave' => [],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify null when not configured
        $this->assertNull($weaveConfig->getAggregationTemplate());
    }
}

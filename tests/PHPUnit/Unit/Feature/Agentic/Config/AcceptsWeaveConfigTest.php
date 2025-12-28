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
 * Acceptance Criterion: AiConfigFeature accepts weave configuration in constructor
 *
 * Criticality: contract
 * Intent: Enables declarative weave defaults through configuration object
 *
 * @spec /specs/features/agentic.yaml:391-394
 */
#[Group('ai'), Group('weave'), Group('config')]
final class AcceptsWeaveConfigTest extends TestCase
{
    #[Test]
    public function aiConfigAcceptsWeaveConfiguration(): void
    {
        // Create AiConfigFeature with weave configuration
        $aiConfig = new AiConfigFeature([
            'weave' => [
                'defaultMaxIterations' => 5,
                'defaultBackend' => 'openai',
                'defaultComplexity' => 'high',
                'promptTemplates' => [
                    'planning' => 'custom planning prompt',
                    'aggregation' => 'custom aggregation prompt',
                    'continuation' => 'custom continuation prompt',
                ],
            ],
        ]);

        // Invoke feature to supply WeaveConfig
        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        // Verify WeaveConfig was supplied
        $weaveConfig = $chainMail->get(WeaveConfig::class);

        $this->assertInstanceOf(WeaveConfig::class, $weaveConfig);
        $this->assertSame(5, $weaveConfig->getMaxIterations());
        $this->assertSame('openai', $weaveConfig->getBackend());
        $this->assertSame('high', $weaveConfig->getComplexity());
        $this->assertSame('custom planning prompt', $weaveConfig->getPlanningTemplate());
        $this->assertSame('custom aggregation prompt', $weaveConfig->getAggregationTemplate());
        $this->assertSame('custom continuation prompt', $weaveConfig->getContinuationTemplate());
    }
}

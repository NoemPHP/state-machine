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
 * Acceptance Criterion: AiConfigFeature weave.defaultComplexity sets default complexity level
 *
 * Criticality: contract
 * Intent: Allows project-wide complexity preference for weave AI calls
 *
 * @spec /specs/features/agentic.yaml:406-409
 */
#[Group('ai'), Group('weave'), Group('config')]
final class DefaultComplexityTest extends TestCase
{
    #[Test]
    public function weaveDefaultComplexityIsConfigurable(): void
    {
        // Configure with custom complexity
        $aiConfig = new AiConfigFeature([
            'weave' => [
                'defaultComplexity' => 'low',
            ],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify the default complexity is set
        $this->assertSame('low', $weaveConfig->getComplexity());
    }

    #[Test]
    public function defaultComplexityCanBeNull(): void
    {
        // No complexity configured
        $aiConfig = new AiConfigFeature([
            'weave' => [],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify it can be null
        $this->assertNull($weaveConfig->getComplexity());
    }
}

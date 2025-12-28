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
 * Acceptance Criterion: AiConfigFeature weave.defaultMaxIterations sets default iteration limit
 *
 * Criticality: contract
 * Intent: Allows project-wide iteration limit customization through configuration
 *
 * @spec /specs/features/agentic.yaml:396-399
 */
#[Group('ai'), Group('weave'), Group('config')]
final class DefaultMaxIterationsTest extends TestCase
{
    #[Test]
    public function weaveDefaultMaxIterationsIsConfigurable(): void
    {
        // Configure with custom maxIterations
        $aiConfig = new AiConfigFeature([
            'weave' => [
                'defaultMaxIterations' => 10,
            ],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify the default iteration limit is set
        $this->assertSame(10, $weaveConfig->getMaxIterations());
    }

    #[Test]
    public function defaultMaxIterationsFallsBackToThree(): void
    {
        // No maxIterations configured
        $aiConfig = new AiConfigFeature([
            'weave' => [],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify fallback to 3
        $this->assertSame(3, $weaveConfig->getMaxIterations());
    }
}

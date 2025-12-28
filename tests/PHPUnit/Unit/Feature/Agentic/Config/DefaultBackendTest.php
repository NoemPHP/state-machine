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
 * Acceptance Criterion: AiConfigFeature weave.defaultBackend sets default backend for weave
 *
 * Criticality: contract
 * Intent: Allows project-wide backend preference for agentic operations
 *
 * @spec /specs/features/agentic.yaml:401-404
 */
#[Group('ai'), Group('weave'), Group('config')]
final class DefaultBackendTest extends TestCase
{
    #[Test]
    public function weaveDefaultBackendIsConfigurable(): void
    {
        // Configure with custom backend
        $aiConfig = new AiConfigFeature([
            'weave' => [
                'defaultBackend' => 'ollama',
            ],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify the default backend is set
        $this->assertSame('ollama', $weaveConfig->getBackend());
    }

    #[Test]
    public function defaultBackendFallsBackToAnthropic(): void
    {
        // No backend configured
        $aiConfig = new AiConfigFeature([
            'weave' => [],
        ]);

        $chainMail = new ChainMail();
        $aiConfig($chainMail);

        $weaveConfig = $chainMail->get(WeaveConfig::class);

        // Verify fallback to 'anthropic'
        $this->assertSame('anthropic', $weaveConfig->getBackend());
    }
}

<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Ollama;

use Noem\State\Feature\Ai\Backend\OllamaBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OllamaBackend accepts optional baseUrl configuration
 *
 * Intent: Enables runtime configuration of Ollama endpoint, supporting local
 * and remote deployments
 */
#[Group('ai'), Group('ollama-backend-implementation')]
class AcceptsConfigurationTest extends TestCase
{
    #[Test]
    public function acceptsOptionalBaseUrl(): void
    {
        $backend = new OllamaBackend(
            baseUrl: 'http://custom-ollama:11434'
        );

        $this->assertInstanceOf(OllamaBackend::class, $backend);
    }

    #[Test]
    public function canBeInstantiatedWithoutConfiguration(): void
    {
        $backend = new OllamaBackend();

        $this->assertInstanceOf(OllamaBackend::class, $backend);
    }
}

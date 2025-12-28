<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Anthropic;

use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AnthropicBackend accepts optional apiKey and baseUrl configuration
 *
 * Intent: Enables runtime configuration of API credentials and endpoint,
 * supporting both cloud and custom deployments
 */
#[Group('ai'), Group('anthropic-backend-implementation')]
class AcceptsConfigurationTest extends TestCase
{
    #[Test]
    public function acceptsOptionalApiKeyAndBaseUrl(): void
    {
        $backend = new AnthropicBackend(
            apiKey: 'test-api-key',
            baseUrl: 'https://custom.anthropic.com'
        );

        $this->assertInstanceOf(AnthropicBackend::class, $backend);
    }

    #[Test]
    public function canBeInstantiatedWithoutConfiguration(): void
    {
        $backend = new AnthropicBackend();

        $this->assertInstanceOf(AnthropicBackend::class, $backend);
    }
}

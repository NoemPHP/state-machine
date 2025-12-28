<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\OpenAi;

use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OpenAiBackend accepts optional apiKey and baseUrl configuration
 *
 * Intent: Enables runtime configuration of API credentials and endpoint,
 * supporting both cloud and local deployments
 */
#[Group('ai'), Group('openai-backend-implementation')]
class AcceptsConfigurationTest extends TestCase
{
    #[Test]
    public function acceptsOptionalApiKeyAndBaseUrl(): void
    {
        $backend = new OpenAiBackend(
            apiKey: 'test-api-key',
            baseUrl: 'https://custom.openai.com'
        );

        $this->assertInstanceOf(OpenAiBackend::class, $backend);
    }

    #[Test]
    public function canBeInstantiatedWithoutConfiguration(): void
    {
        $backend = new OpenAiBackend();

        $this->assertInstanceOf(OpenAiBackend::class, $backend);
    }
}

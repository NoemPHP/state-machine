<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use Noem\State\Feature\Ai\Backend\OllamaBackend;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Backend mesh contains backends keyed by name ('openai', 'ollama', 'anthropic')
 *
 * Intent: Establishes backend naming convention using provider names as keys,
 * enabling backend selection via name rather than model patterns
 */
#[Group('ai'), Group('backend-mesh-infrastructure')]
class MeshContainsNamedBackendsTest extends TestCase
{
    #[Test]
    public function meshContainsBackendsKeyedByName(): void
    {
        $chainMail = new ChainMail();

        $feature = new AiFeature();
        $feature($chainMail);

        $backends = $chainMail->get(Mesh::class);

        // Verify backends exist with correct names
        $this->assertInstanceOf(OpenAiBackend::class, $backends['openai']);
        $this->assertInstanceOf(OllamaBackend::class, $backends['ollama']);
        $this->assertInstanceOf(AnthropicBackend::class, $backends['anthropic']);
    }
}

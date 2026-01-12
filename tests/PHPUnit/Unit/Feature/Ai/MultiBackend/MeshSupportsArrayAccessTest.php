<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Backend mesh supports array-like access pattern ($backends['openai'])
 *
 * Intent: Provides intuitive backend access through ArrayAccess interface,
 * enabling simple backend retrieval by name
 */
#[Group('ai'), Group('backend-mesh-infrastructure')]
class MeshSupportsArrayAccessTest extends TestCase
{
    #[Test]
    public function meshSupportsArrayLikeAccess(): void
    {
        $chainMail = new ChainMail();

        $feature = new AiFeature();
        $feature($chainMail);

        $backends = $chainMail->get(Mesh::class);

        // Test array access pattern
        $openai = $backends['openai'];
        $this->assertInstanceOf(OpenAiBackend::class, $openai);

        // Test isset
        $this->assertTrue(isset($backends['openai']));
        $this->assertTrue(isset($backends['ollama']));
        $this->assertTrue(isset($backends['anthropic']));
        $this->assertFalse(isset($backends['nonexistent']));
    }
}

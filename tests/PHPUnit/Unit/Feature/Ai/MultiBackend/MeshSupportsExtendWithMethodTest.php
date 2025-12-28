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
 * Acceptance Criterion: Backend mesh supports extendWith() method for adding backend sources
 *
 * Intent: Enables extending mesh with additional backend collections,
 * allowing features to add multiple backends at once
 */
#[Group('ai'), Group('backend-mesh-infrastructure')]
class MeshSupportsExtendWithMethodTest extends TestCase
{
    #[Test]
    public function meshSupportsExtendWithMethod(): void
    {
        $chainMail = new ChainMail();

        $feature = new AiFeature();
        $feature($chainMail);

        $backends = $chainMail->get(Mesh::class);

        // Create an extension with additional backend
        $extension = [
            'custom' => new OpenAiBackend(),
        ];

        // Extend the mesh with additional backends
        $backends->extendWith($extension);

        // Verify the custom backend is accessible
        $this->assertInstanceOf(OpenAiBackend::class, $backends['custom']);

        // Verify original backends are still accessible
        $this->assertInstanceOf(OpenAiBackend::class, $backends['openai']);
    }
}
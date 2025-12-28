<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Ollama;

use Noem\State\Feature\Ai\Backend\BackendInterface;
use Noem\State\Feature\Ai\Backend\OllamaBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OllamaBackend implements BackendInterface
 *
 * Intent: Ensures Ollama backend conforms to backend abstraction contract
 */
#[Group('ai'), Group('ollama-backend-implementation')]
class ImplementsBackendInterfaceTest extends TestCase
{
    #[Test]
    public function implementsBackendInterface(): void
    {
        $backend = new OllamaBackend();

        $this->assertInstanceOf(BackendInterface::class, $backend);
    }
}

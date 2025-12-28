<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\OpenAi;

use Noem\State\Feature\Ai\Backend\BackendInterface;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OpenAiBackend implements BackendInterface
 *
 * Intent: Ensures OpenAI backend conforms to backend abstraction contract
 */
#[Group('ai'), Group('openai-backend-implementation')]
class ImplementsBackendInterfaceTest extends TestCase
{
    #[Test]
    public function implementsBackendInterface(): void
    {
        $backend = new OpenAiBackend();

        $this->assertInstanceOf(BackendInterface::class, $backend);
    }
}

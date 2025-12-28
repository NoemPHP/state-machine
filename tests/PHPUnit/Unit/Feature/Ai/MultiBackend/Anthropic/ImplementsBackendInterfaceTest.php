<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\Anthropic;

use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use Noem\State\Feature\Ai\Backend\BackendInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AnthropicBackend implements BackendInterface
 *
 * Intent: Ensures Anthropic backend conforms to backend abstraction contract
 */
#[Group('ai'), Group('anthropic-backend-implementation')]
class ImplementsBackendInterfaceTest extends TestCase
{
    #[Test]
    public function implementsBackendInterface(): void
    {
        $backend = new AnthropicBackend();

        $this->assertInstanceOf(BackendInterface::class, $backend);
    }
}

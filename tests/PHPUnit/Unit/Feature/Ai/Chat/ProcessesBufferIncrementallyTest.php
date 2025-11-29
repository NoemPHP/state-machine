<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat processes response buffer incrementally
 */
#[Group('ai'), Group('chat-api')]
class ProcessesBufferIncrementallyTest extends TestCase
{
    #[Test]
    public function processesBufferIncrementallyTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion processes response buffer incrementally
 */
#[Group('ai'), Group('completion-api')]
class ProcessesBufferIncrementallyTest extends TestCase
{
    #[Test]
    public function processesBufferIncrementally(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

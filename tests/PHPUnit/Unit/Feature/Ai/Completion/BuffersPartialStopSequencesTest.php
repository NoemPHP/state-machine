<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion buffers partial stop sequences
 */
#[Group('ai'), Group('completion-api')]
class BuffersPartialStopSequencesTest extends TestCase
{
    #[Test]
    public function buffersPartialStopSequencesTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

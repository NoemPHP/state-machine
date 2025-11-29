<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion yields text chunks from streaming API
 */
#[Group('ai'), Group('completion-api')]
class YieldsTextChunksTest extends TestCase
{
    #[Test]
    public function yieldsTextChunks(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

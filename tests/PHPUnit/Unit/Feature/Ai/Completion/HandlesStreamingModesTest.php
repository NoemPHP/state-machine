<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion handles streaming and non-streaming responses
 */
#[Group('ai'), Group('completion-api')]
class HandlesStreamingModesTest extends TestCase
{
    #[Test]
    public function handlesStreamingModesTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

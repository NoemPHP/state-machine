<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion accepts string prompt and builds Request automatically
 */
#[Group('ai'), Group('completion-api')]
class AcceptsStringPromptTest extends TestCase
{
    #[Test]
    public function acceptsStringPrompt(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

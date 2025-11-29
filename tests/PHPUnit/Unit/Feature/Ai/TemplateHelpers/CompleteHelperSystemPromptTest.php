<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Complete helper includes system prompt and rules
 */
#[Group('ai'), Group('template-helpers')]
class CompleteHelperSystemPromptTest extends TestCase
{
    #[Test]
    public function completeHelperSystemPromptTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

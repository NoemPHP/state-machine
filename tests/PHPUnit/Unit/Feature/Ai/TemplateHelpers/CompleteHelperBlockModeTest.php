<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Complete helper supports block mode with instructions
 */
#[Group('ai'), Group('template-helpers')]
class CompleteHelperBlockModeTest extends TestCase
{
    #[Test]
    public function completeHelperBlockModeTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

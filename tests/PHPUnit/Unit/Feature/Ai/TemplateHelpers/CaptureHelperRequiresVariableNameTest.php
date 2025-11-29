<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper requires target variable name as first argument
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperRequiresVariableNameTest extends TestCase
{
    #[Test]
    public function captureHelperRequiresVariableNameTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

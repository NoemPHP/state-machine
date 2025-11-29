<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper uses JSON schema response format
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperUsesJsonSchemaTest extends TestCase
{
    #[Test]
    public function captureHelperUsesJsonSchemaTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}

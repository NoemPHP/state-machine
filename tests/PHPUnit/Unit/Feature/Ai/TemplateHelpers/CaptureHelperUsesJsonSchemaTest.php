<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper uses JSON schema response format from schema
 * parameter or defaults to array of strings
 *
 * Intent: Enables custom structured extraction by accepting optional schema parameter,
 * falling back to default array<string> schema for backward compatibility
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperUsesJsonSchemaTest extends TestCase
{
    #[Test]
    public function captureHelperUsesDefaultArrayOfStringsSchema(): void
    {
        // When no schema parameter provided, should use default array<string> schema
        $this->markTestIncomplete('Implementation needed: default schema');
    }

    #[Test]
    public function captureHelperUsesCustomSchema(): void
    {
        // When schema parameter provided, should use that schema instead
        $this->markTestIncomplete('Implementation needed: custom schema');
    }
}

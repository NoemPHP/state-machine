<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Async object has optional enabled flag (default true)
 * Intent: Provides explicit opt-in for async behavior in YAML, preventing accidental async registration
 */
#[Group('async'), Group('unit'), Group('yaml')]
class YamlEnabledFlagTest extends TestCase
{
    public function testAsyncObjectHasOptionalEnabledFlagDefaultTrue(): void
    {
        // RED TEST: YAML implementation pending
        $this->markTestIncomplete('YAML feature awaiting implementation');
    }
}

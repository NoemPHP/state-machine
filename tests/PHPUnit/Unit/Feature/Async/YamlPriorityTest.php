<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Async object accepts priority as string enum (low, normal, high)
 * Intent: Configures task priority in YAML using human-readable values, supporting declarative scheduling
 */
#[Group('async'), Group('unit'), Group('yaml')]
class YamlPriorityTest extends TestCase
{
    public function testAsyncObjectAcceptsPriorityAsStringEnum(): void
    {
        // RED TEST: YAML implementation pending
        $this->markTestIncomplete('YAML feature awaiting implementation');
    }
}

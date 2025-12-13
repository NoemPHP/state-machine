<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Async object accepts timeout as float in seconds
 * Intent: Configures task timeout in YAML, supporting declarative timeout limits
 */
#[Group('async'), Group('unit'), Group('yaml')]
class YamlTimeoutTest extends TestCase
{
    public function testAsyncObjectAcceptsTimeoutAsFloatInSeconds(): void
    {
        // RED TEST: YAML implementation pending
        $this->markTestIncomplete('YAML feature awaiting implementation');
    }
}

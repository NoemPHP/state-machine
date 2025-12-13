<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Async object accepts throttle as float in seconds
 * Intent: Configures throttle interval in YAML, supporting declarative rate limiting
 */
#[Group('async'), Group('unit'), Group('yaml')]
class YamlThrottleTest extends TestCase
{
    public function testAsyncObjectAcceptsThrottleAsFloatInSeconds(): void
    {
        // RED TEST: YAML implementation pending
        $this->markTestIncomplete('YAML feature awaiting implementation');
    }
}

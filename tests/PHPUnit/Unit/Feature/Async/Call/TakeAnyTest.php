<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use Noem\State\Feature\Async\Call;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.takeAny pauses task until any matching event occurs
 */
#[Group('async'), Group('call-helpers')]
class TakeAnyTest extends TestCase
{
    public function testTakeAnyCreatesCallObject(): void
    {
        $carry = null;
        $matchers = [
            \stdClass::class => fn($e) => true,
            \Exception::class => fn($e) => true,
        ];
        
        $takeAnyCall = Call::takeAny($carry, ...$matchers);
        
        $this->assertInstanceOf(Call::class, $takeAnyCall, 'Call.takeAny should return a Call object');
    }
    
    public function testTakeAnyAcceptsMultipleMatchers(): void
    {
        $carry = ['data'];
        $matcher1 = \stdClass::class;
        $matcher2 = \Exception::class;
        $matcher3 = \RuntimeException::class;
        
        $takeAnyCall = Call::takeAny($carry, $matcher1, $matcher2, $matcher3);
        
        $this->assertInstanceOf(Call::class, $takeAnyCall, 'Call.takeAny should accept multiple matchers');
    }
    
    public function testTakeAnyRequiresListenerProvider(): void
    {
        // Note: Full testing of takeAny() functionality requires listener provider infrastructure
        // which is part of a broader event system integration
        $this->markTestIncomplete(
            'Full takeAny() functionality testing requires CoroutineScheduler->listenerProvider() ' .
            'implementation. This test verifies Call object creation only.'
        );
    }
}

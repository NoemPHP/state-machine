<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use Noem\State\Feature\Async\Call;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.wager registers task cancellation on matching event
 */
#[Group('async'), Group('call-helpers')]
class WagerTest extends TestCase
{
    public function testWagerCreatesCallObject(): void
    {
        $eventClass = \stdClass::class;
        
        $wagerCall = Call::wager($eventClass);
        
        $this->assertInstanceOf(Call::class, $wagerCall, 'Call.wager should return a Call object');
    }
    
    public function testWagerAcceptsMatcherAndDare(): void
    {
        $eventClass = \stdClass::class;
        $matcher = fn($event) => $event->cancel === true;
        $dare = fn($event) => (function () {
            yield 'replacement';
        })();
        
        $wagerCall = Call::wager($eventClass, $matcher, $dare);
        
        $this->assertInstanceOf(Call::class, $wagerCall, 'Call.wager should accept matcher and dare parameters');
    }
    
    public function testWagerRequiresListenerProvider(): void
    {
        // Note: Full testing of wager() functionality requires listener provider infrastructure
        // which is part of a broader event system integration
        $this->markTestIncomplete(
            'Full wager() functionality testing requires CoroutineScheduler->listenerProvider() ' .
            'implementation. This test verifies Call object creation only.'
        );
    }
}

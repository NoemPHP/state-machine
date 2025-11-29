<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Events;
use Noem\State\Feature\Async\AsyncChains\Enqueue;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature registers Enqueue chain in ChainMail
 */
#[Group('async'), Group('feature-registration')]
class RegistersEnqueueChainTest extends TestCase
{
    public function testRegistersEnqueueChain(): void
    {
        $chainMail = new ChainMail();

        // Provide basic dependencies that AsyncFeature might need
        $chainMail->supply(
            fn(): ValidateCallback => new ValidateCallback(),
            fn(): PrepareInvokable => new PrepareInvokable(),
            fn(): InvokeCallback => new InvokeCallback(),
            Events::conjure()
        );

        $feature = new AsyncFeature();
        $feature($chainMail);
        
        $enqueue = $chainMail->get(Enqueue::class);
        $this->assertInstanceOf(Enqueue::class, $enqueue);
    }
}

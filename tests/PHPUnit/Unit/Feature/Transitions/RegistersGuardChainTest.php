<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Feature\Transitions\Chains\Guard;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionsFeature registers Guard chain in ChainMail
 */
#[Group('transitions')]
#[Group('feature-registration')]
class RegistersGuardChainTest extends TestCase
{
    public function testRegistersGuardChain(): void
    {
        $chainMail = new ChainMail();

        // Provide the dependencies that Guard requires
        $chainMail->supply(
            fn(): InvokeCallback => new InvokeCallback(),
            fn(): PrepareInvokable => new PrepareInvokable()
        );

        $feature = new TransitionsFeature();
        $feature($chainMail);

        $guard = $chainMail->get(Guard::class);
        $this->assertInstanceOf(Guard::class, $guard);
    }
}

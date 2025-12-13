<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Events;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionsFeature registers DoTransition chain in ChainMail
 */
#[Group('transitions')]
#[Group('feature-registration')]
class RegistersDoTransitionTest extends TestCase
{
    public function testRegistersDoTransitionChain(): void
    {
        $chainMail = new ChainMail();

        // Provide the dependencies that DoTransition and Events require
        $chainMail->supply(
            fn(): ConnectedRegions => new ConnectedRegions(),
            fn(): ValidateCallback => new ValidateCallback(),
            fn(): PrepareInvokable => new PrepareInvokable(),
            fn(): InvokeCallback => new InvokeCallback(),
            fn(): \Noem\State\Callbacks\CallbackRegistry => new \Noem\State\Callbacks\CallbackRegistry(),
            Events::conjure()
        );

        $feature = new TransitionsFeature();
        $feature($chainMail);

        $doTransition = $chainMail->get(DoTransition::class);
        $this->assertInstanceOf(DoTransition::class, $doTransition);
    }
}

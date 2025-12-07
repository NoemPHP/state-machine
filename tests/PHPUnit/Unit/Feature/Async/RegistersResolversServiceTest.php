<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Events;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Resolvers;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature registers Resolvers service in ChainMail
 */
#[Group('async'), Group('feature-registration')]
class RegistersResolversServiceTest extends TestCase
{
    public function testRegistersResolversService(): void
    {
        $chainMail = new ChainMail();

        // Provide basic dependencies
        $chainMail->supply(
            fn(): ValidateCallback => new ValidateCallback(),
            fn(): PrepareInvokable => new PrepareInvokable(),
            fn(): InvokeCallback => new InvokeCallback(),
            Events::conjure()
        );

        $feature = new AsyncFeature();
        $feature($chainMail);

        $resolvers = $chainMail->get(Resolvers::class);
        $this->assertInstanceOf(Resolvers::class, $resolvers);
    }
}

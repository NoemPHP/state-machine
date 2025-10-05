<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Guard;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Feature\Transitions\Chains\Guard;
use Noem\State\Feature\Transitions\Chains\Params;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Acceptance Criterion: Guard chain throws RuntimeException for invalid return type
 */
#[Group('transitions')]
#[Group('guard-validation')]
class ThrowsOnInvalidReturnTypeTest extends TestCase
{
    public function testThrowsForStringReturnType(): void
    {
        $guard = new Guard(new InvokeCallback(), new PrepareInvokable());
        $region = (new RegionBuilder())->setStates('start', 'end')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): string => 'invalid';
        
        $context = new Params\Guard($region, 'start', 'end', $handler, $trigger);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Invalid guard callback for a transition from 'start' to 'end'");
        $guard->call($context);
    }
    
    public function testThrowsForNoReturnType(): void
    {
        $guard = new Guard(new InvokeCallback(), new PrepareInvokable());
        $region = (new RegionBuilder())->setStates('start', 'end')->build();
        $trigger = new stdClass();
        $handler = fn(object $t) => true;
        
        $context = new Params\Guard($region, 'start', 'end', $handler, $trigger);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Invalid guard callback for a transition from 'start' to 'end'");
        $guard->call($context);
    }
}

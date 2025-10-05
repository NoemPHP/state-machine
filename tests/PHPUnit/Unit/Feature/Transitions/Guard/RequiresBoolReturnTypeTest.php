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

/**
 * Acceptance Criterion: Guard chain requires bool return type
 */
#[Group('transitions')]
#[Group('guard-validation')]
class RequiresBoolReturnTypeTest extends TestCase
{
    public function testAcceptsGuardWithBoolReturnType(): void
    {
        $builder = new RegionBuilder();
        $region = $builder->setStates('a', 'b')->build();
        
        $invokeCallback = $builder->chainMail->get(InvokeCallback::class);
        $prepareInvokable = $builder->chainMail->get(PrepareInvokable::class);
        
        $guard = new Guard($invokeCallback, $prepareInvokable);
        
        $validGuard = fn(object $t): bool => true;
        $context = new Params\Guard($region, 'a', 'b', $validGuard, (object)[]);
        
        $result = $guard->call($context);
        
        $this->assertIsBool($result);
    }
    
    public function testThrowsOnNonBoolReturnType(): void
    {
        $builder = new RegionBuilder();
        $region = $builder->setStates('a', 'b')->build();
        
        $invokeCallback = $builder->chainMail->get(InvokeCallback::class);
        $prepareInvokable = $builder->chainMail->get(PrepareInvokable::class);
        
        $guard = new Guard($invokeCallback, $prepareInvokable);
        
        $invalidGuard = fn(object $t): string => 'yes';
        $context = new Params\Guard($region, 'a', 'b', $invalidGuard, (object)[]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Guards must return bool');
        
        $guard->call($context);
    }
}

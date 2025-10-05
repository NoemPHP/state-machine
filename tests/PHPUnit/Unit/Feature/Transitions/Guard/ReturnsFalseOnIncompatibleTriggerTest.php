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
use stdClass;

class CustomEvent
{
    public function __construct(public string $data)
    {
    }
}

/**
 * Acceptance Criterion: Guard chain returns false when trigger type incompatible
 */
#[Group('transitions')]
#[Group('guard-validation')]
class ReturnsFalseOnIncompatibleTriggerTest extends TestCase
{
    public function testReturnsFalseForIncompatibleType(): void
    {
        $guard = new Guard(new InvokeCallback(), new PrepareInvokable());
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new stdClass();
        $handler = fn(CustomEvent $t): bool => true;
        
        $context = new Params\Guard($region, 'a', 'b', $handler, $trigger);
        $result = $guard->call($context);
        
        $this->assertFalse($result);
    }
    
    public function testReturnsTrueForCompatibleType(): void
    {
        $guard = new Guard(new InvokeCallback(), new PrepareInvokable());
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new CustomEvent('test');
        $handler = fn(CustomEvent $t): bool => $t->data === 'test';
        
        $context = new Params\Guard($region, 'a', 'b', $handler, $trigger);
        $result = $guard->call($context);
        
        $this->assertTrue($result);
    }
}

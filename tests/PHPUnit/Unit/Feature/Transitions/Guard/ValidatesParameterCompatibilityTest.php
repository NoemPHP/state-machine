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

/**
 * Acceptance Criterion: Guard chain validates callback parameter compatibility with trigger
 */
#[Group('transitions')]
#[Group('guard-validation')]
class ValidatesParameterCompatibilityTest extends TestCase
{
    public function testAcceptsCompatibleTriggerType(): void
    {
        $guard = new Guard(new InvokeCallback(), new PrepareInvokable());
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => true;
        
        $context = new Params\Guard($region, 'a', 'b', $handler, $trigger);
        $result = $guard->call($context);
        
        $this->assertTrue($result);
    }
    
    public function testAcceptsSpecificTypeParameter(): void
    {
        $guard = new Guard(new InvokeCallback(), new PrepareInvokable());
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new stdClass();
        $handler = fn(stdClass $t): bool => true;
        
        $context = new Params\Guard($region, 'a', 'b', $handler, $trigger);
        $result = $guard->call($context);
        
        $this->assertTrue($result);
    }
}

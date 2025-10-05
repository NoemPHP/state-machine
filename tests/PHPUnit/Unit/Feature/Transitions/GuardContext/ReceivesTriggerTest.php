<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\GuardContext;

use Noem\State\Feature\Transitions\Chains\Params\Guard;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Guard chain receives context with trigger payload
 */
#[Group('transitions')]
#[Group('guard-context')]
class ReceivesTriggerTest extends TestCase
{
    public function testGuardContextContainsTriggerPayload(): void
    {
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new stdClass();
        $trigger->data = 'test';
        $handler = fn(object $t): bool => true;
        
        $context = new Guard($region, 'a', 'b', $handler, $trigger);
        
        $this->assertSame($trigger, $context->trigger);
    }
    
    public function testGuardContextTriggerIsAccessible(): void
    {
        $region = (new RegionBuilder())->setStates('start', 'end')->build();
        $trigger = (object)['id' => 123, 'value' => 'test'];
        $handler = fn(object $t): bool => true;
        
        $context = new Guard($region, 'start', 'end', $handler, $trigger);
        
        $this->assertEquals(123, $context->trigger->id);
        $this->assertEquals('test', $context->trigger->value);
    }
}

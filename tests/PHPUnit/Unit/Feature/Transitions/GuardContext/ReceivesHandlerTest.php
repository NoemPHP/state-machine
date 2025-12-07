<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\GuardContext;

use Noem\State\Feature\Transitions\Chains\Params\Guard;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Guard chain receives context with handler callable
 */
#[Group('transitions')]
#[Group('guard-context')]
class ReceivesHandlerTest extends TestCase
{
    public function testGuardContextContainsHandlerCallable(): void
    {
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => true;

        $context = new Guard($region, 'a', 'b', $handler, $trigger);

        $this->assertSame($handler, $context->handler);
    }

    public function testGuardContextHandlerIsCallable(): void
    {
        $region = (new RegionBuilder())->setStates('start', 'end')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => $t->ready ?? false;

        $context = new Guard($region, 'start', 'end', $handler, $trigger);

        $this->assertTrue(is_callable($context->handler));
    }
}

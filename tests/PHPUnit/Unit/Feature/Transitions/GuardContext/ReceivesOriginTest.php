<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\GuardContext;

use Noem\State\Feature\Transitions\Chains\Params\Guard;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Guard chain receives context with origin state
 */
#[Group('transitions')]
#[Group('guard-context')]
class ReceivesOriginTest extends TestCase
{
    public function testGuardContextContainsOriginState(): void
    {
        $region = (new RegionBuilder())->setStates('start', 'middle', 'end')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => true;

        $context = new Guard($region, 'start', 'end', $handler, $trigger);

        $this->assertEquals('start', $context->origin);
    }

    public function testGuardContextOriginMatchesSourceState(): void
    {
        $region = (new RegionBuilder())->setStates('idle', 'processing')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => true;

        $context = new Guard($region, 'idle', 'processing', $handler, $trigger);

        $this->assertEquals('idle', $context->origin);
        $this->assertEquals($region->currentState(), $context->origin);
    }
}

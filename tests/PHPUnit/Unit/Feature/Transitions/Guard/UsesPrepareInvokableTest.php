<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Guard;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Feature\Transitions\Chains\Guard;
use Noem\State\Feature\Transitions\Chains\Params;
use Noem\State\Middleware\Chain;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Guard chain uses PrepareInvokable for callback preparation
 */
#[Group('transitions')]
#[Group('guard-validation')]
class UsesPrepareInvokableTest extends TestCase
{
    public function testInvokesPrepareInvokable(): void
    {
        $prepared = false;
        $prepareInvokable = new class ($prepared) extends PrepareInvokable {
            public function __construct(private bool &$prepared)
            {
                parent::__construct();
                $this->link(function (Callback $ctx, callable $next) use (&$prepared): callable {
                    $prepared = true;
                    return $next($ctx);
                });
            }
        };
        
        $guard = new Guard(new InvokeCallback(), $prepareInvokable);
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => true;
        
        $context = new Params\Guard($region, 'a', 'b', $handler, $trigger);
        $guard->call($context);
        
        $this->assertTrue($prepared);
    }
}

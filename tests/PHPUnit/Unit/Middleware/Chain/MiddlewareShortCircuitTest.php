<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Middleware can short-circuit execution by not calling next()
 */
#[Group('middleware')]
#[Group('chain')]
class MiddlewareShortCircuitTest extends TestCase
{
    public function testMiddlewareCanShortCircuitExecution(): void
    {
        $providerCalled = false;

        $shortCircuit = function ($context, $next) {
            return 'short-circuited';
            // next() is never called
        };

        $chain = new Chain(
            function ($c) use (&$providerCalled) {
                $providerCalled = true;
                return "provider:$c";
            },
            [$shortCircuit]
        );

        $result = $chain->call('test');

        $this->assertEquals('short-circuited', $result);
        $this->assertFalse($providerCalled, 'Provider should not be called when middleware short-circuits');
    }

    public function testConditionalShortCircuit(): void
    {
        $middleware = function ($context, $next) {
            if ($context === 'stop') {
                return 'stopped';
            }
            return $next($context);
        };

        $chain = new Chain(fn($c) => "provider:$c", [$middleware]);

        $this->assertEquals('stopped', $chain->call('stop'));
        $this->assertEquals('provider:continue', $chain->call('continue'));
    }

    public function testShortCircuitPreventsSubsequentMiddleware(): void
    {
        $middleware1Called = false;
        $middleware2Called = false;

        $middleware1 = function ($context, $next) use (&$middleware1Called) {
            $middleware1Called = true;
            return 'stopped';
        };

        $middleware2 = function ($context, $next) use (&$middleware2Called) {
            $middleware2Called = true;
            return $next($context);
        };

        $chain = new Chain(fn($c) => $c, [$middleware1, $middleware2]);
        $chain->call('test');

        $this->assertTrue($middleware1Called);
        $this->assertFalse($middleware2Called, 'Subsequent middleware should not be called');
    }
}

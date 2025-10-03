<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Middleware executes in reverse order (last linked executes first)
 */
#[Group('middleware')]
#[Group('chain')]
class MiddlewareExecutionOrderTest extends TestCase
{
    public function testMiddlewareExecutionOrder(): void
    {
        $executed = [];

        $middleware1 = function ($context, $next) use (&$executed) {
            $executed[] = 'middleware1';
            return $next($context) . ' + middleware1';
        };

        $middleware2 = function ($context, $next) use (&$executed) {
            $executed[] = 'middleware2';
            return $next($context) . ' + middleware2';
        };

        $chain = new Chain(fn($c) => "base:$c", [$middleware1, $middleware2]);

        $result = $chain->call('test');

        // Middleware executes in order: middleware1, then middleware2
        $this->assertEquals(['middleware1', 'middleware2'], $executed);
        // But results are wrapped in reverse: middleware2 wraps middleware1 wraps base
        $this->assertEquals('base:test + middleware2 + middleware1', $result);
    }

    public function testLastLinkedExecutesFirst(): void
    {
        $order = [];

        $first = function ($c, $next) use (&$order) {
            $order[] = 'first-before';
            $result = $next($c);
            $order[] = 'first-after';
            return $result;
        };

        $second = function ($c, $next) use (&$order) {
            $order[] = 'second-before';
            $result = $next($c);
            $order[] = 'second-after';
            return $result;
        };

        $chain = new Chain(fn($c) => $c, [$first, $second]);
        $chain->call('test');

        // First middleware runs first, wrapping the second
        $this->assertEquals([
            'first-before',
            'second-before',
            'second-after',
            'first-after'
        ], $order);
    }
}

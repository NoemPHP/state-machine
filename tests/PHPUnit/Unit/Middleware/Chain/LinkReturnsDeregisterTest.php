<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Middleware registration returns a function to de-register them later on
 */
#[Group('middleware')]
#[Group('chain')]
#[Group('lifecycle-management')]
class LinkReturnsDeregisterTest extends TestCase
{
    public function testLinkReturnsCallable(): void
    {
        $chain = new Chain(fn($c) => $c);
        $middleware = fn($c, $next) => $next($c);

        $deregister = $chain->link($middleware);

        $this->assertIsCallable($deregister);
    }

    public function testDeregisterRemovesMiddleware(): void
    {
        $executed = false;
        $middleware = function ($c, $next) use (&$executed) {
            $executed = true;
            return $next($c);
        };

        $chain = new Chain(fn($c) => $c);
        $deregister = $chain->link($middleware);

        // First call - middleware should execute
        $chain->call('test');
        $this->assertTrue($executed, 'Middleware should execute before deregister');

        // Deregister the middleware
        $executed = false;
        $deregister();

        // Second call - middleware should not execute
        $chain->call('test');
        $this->assertFalse($executed, 'Middleware should not execute after deregister');
    }

    public function testDeregisterDoesNotAffectOtherMiddleware(): void
    {
        $executions = [];

        $middleware1 = function ($c, $next) use (&$executions) {
            $executions[] = 'm1';
            return $next($c);
        };

        $middleware2 = function ($c, $next) use (&$executions) {
            $executions[] = 'm2';
            return $next($c);
        };

        $middleware3 = function ($c, $next) use (&$executions) {
            $executions[] = 'm3';
            return $next($c);
        };

        $chain = new Chain(fn($c) => $c);
        $chain->link($middleware1);
        $deregister2 = $chain->link($middleware2);
        $chain->link($middleware3);

        // All three should execute
        $chain->call('test');
        $this->assertEquals(['m1', 'm2', 'm3'], $executions);

        // Deregister middleware2
        $executions = [];
        $deregister2();

        // Only m1 and m3 should execute
        $chain->call('test');
        $this->assertEquals(['m1', 'm3'], $executions);
    }

    public function testMultipleMiddlewareCanBeDeregistered(): void
    {
        $executions = [];

        $m1 = function ($c, $next) use (&$executions) {
            $executions[] = 'm1';
            return $next($c);
        };
        $m2 = function ($c, $next) use (&$executions) {
            $executions[] = 'm2';
            return $next($c);
        };
        $m3 = function ($c, $next) use (&$executions) {
            $executions[] = 'm3';
            return $next($c);
        };

        $chain = new Chain(fn($c) => $c);
        $deregister1 = $chain->link($m1);
        $deregister2 = $chain->link($m2);
        $deregister3 = $chain->link($m3);

        // All execute
        $chain->call('test');
        $this->assertCount(3, $executions);

        // Deregister all
        $executions = [];
        $deregister1();
        $deregister2();
        $deregister3();

        // None execute
        $chain->call('test');
        $this->assertEmpty($executions);
    }
}

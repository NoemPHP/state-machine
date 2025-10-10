<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Middleware can be linked with prepend option to add at the beginning
 */
#[Group('middleware')]
#[Group('chain')]
class PrependMiddlewareTest extends TestCase
{
    public function testPrependMiddleware(): void
    {
        $order = [];

        $first = function ($c, $next) use (&$order) {
            $order[] = 'first';
            return $next($c);
        };

        $prepended = function ($c, $next) use (&$order) {
            $order[] = 'prepended';
            return $next($c);
        };

        $chain = new Chain(fn($c) => $c, [$first]);
        $chain->link($prepended, true);

        $chain->call('test');

        $this->assertEquals(['prepended', 'first'], $order);
    }

    public function testMultiplePrepends(): void
    {
        $order = [];

        $base = function ($c, $next) use (&$order) {
            $order[] = 'base';
            return $next($c);
        };

        $chain = new Chain(fn($c) => $c, [$base]);

        $chain->link(function ($c, $next) use (&$order) {
            $order[] = 'prepend1';
            return $next($c);
        }, true);

        $chain->link(function ($c, $next) use (&$order) {
            $order[] = 'prepend2';
            return $next($c);
        }, true);

        $chain->call('test');

        // Most recent prepend executes first
        $this->assertEquals(['prepend2', 'prepend1', 'base'], $order);
    }

    public function testPrependVsLink(): void
    {
        $order = [];

        $chain = new Chain(fn($c) => $c);

        $chain->link(function ($c, $next) use (&$order) {
            $order[] = 'linked';
            return $next($c);
        });

        $chain->link(function ($c, $next) use (&$order) {
            $order[] = 'prepended';
            return $next($c);
        }, true);

        $chain->call('test');

        // Prepended should execute before linked
        $this->assertEquals(['prepended', 'linked'], $order);
    }
}

<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A chain supports linking multiple middleware functions in sequence
 */
#[Group('middleware')]
#[Group('chain')]
class LinkMultipleMiddlewareTest extends TestCase
{
    public function testCanLinkAdditionalMiddlewares(): void
    {
        $middleware1 = fn($context, $next) => $next($context) . ' + m1';
        $middleware2 = fn($context, $next) => $next($context) . ' + m2';

        $chain = new Chain(fn($c) => "base:$c");
        $chain->link($middleware1);
        $chain->link($middleware2);

        $result = $chain->call('test');

        $this->assertStringContainsString('base:test', $result);
        $this->assertStringContainsString('+ m1', $result);
        $this->assertStringContainsString('+ m2', $result);
    }

    public function testMultipleLinkedMiddlewareExecute(): void
    {
        $executionOrder = [];

        $chain = new Chain(fn($c) => $c);
        $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'first';
            return $next($c);
        });
        $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'second';
            return $next($c);
        });
        $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'third';
            return $next($c);
        });

        $chain->call('test');

        $this->assertCount(3, $executionOrder);
    }
}

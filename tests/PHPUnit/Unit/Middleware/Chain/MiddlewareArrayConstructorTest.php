<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Multiple middleware can be passed to constructor as an array
 */
#[Group('middleware')]
#[Group('chain')]
class MiddlewareArrayConstructorTest extends TestCase
{
    public function testMultipleMiddlewareAsArrayInConstructor(): void
    {
        $middleware1 = fn($c, $next) => $next($c) . ' + m1';
        $middleware2 = fn($c, $next) => $next($c) . ' + m2';
        $middleware3 = fn($c, $next) => $next($c) . ' + m3';

        $chain = new Chain(
            fn($c) => "base:$c",
            [$middleware1, $middleware2, $middleware3]
        );

        $result = $chain->call('test');

        $this->assertStringContainsString('base:test', $result);
        $this->assertStringContainsString('+ m1', $result);
        $this->assertStringContainsString('+ m2', $result);
        $this->assertStringContainsString('+ m3', $result);
    }

    public function testEmptyMiddlewareArray(): void
    {
        $chain = new Chain(fn($c) => "result:$c", []);

        $result = $chain->call('test');

        $this->assertEquals('result:test', $result);
    }

    public function testSingleMiddlewareInArray(): void
    {
        $middleware = fn($c, $next) => strtoupper($next($c));

        $chain = new Chain(fn($c) => $c, [$middleware]);

        $result = $chain->call('test');

        $this->assertEquals('TEST', $result);
    }

    public function testMiddlewareArrayPreservesOrder(): void
    {
        $order = [];

        $m1 = function ($c, $next) use (&$order) {
            $order[] = 1;
            return $next($c);
        };
        $m2 = function ($c, $next) use (&$order) {
            $order[] = 2;
            return $next($c);
        };
        $m3 = function ($c, $next) use (&$order) {
            $order[] = 3;
            return $next($c);
        };

        $chain = new Chain(fn($c) => $c, [$m1, $m2, $m3]);
        $chain->call('test');

        $this->assertEquals([1, 2, 3], $order);
    }
}

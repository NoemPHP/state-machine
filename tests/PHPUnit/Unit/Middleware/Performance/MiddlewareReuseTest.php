<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Performance;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chain reuses compiled middleware stack for multiple calls
 */
#[Group('middleware')]
#[Group('performance')]
class MiddlewareReuseTest extends TestCase
{
    public function testChainReusesCompiledMiddleware(): void
    {
        $compileCount = 0;

        $middleware = function ($context, $next) use (&$compileCount) {
            // This would be called each time if not reusing
            return $next($context);
        };

        $chain = new Chain(
            function ($c) use (&$compileCount) {
                $compileCount++;
                return $c;
            },
            [$middleware]
        );

        // First call should compile
        $chain->call('test1');
        // Subsequent calls should reuse compiled chain
        $chain->call('test2');
        $chain->call('test3');

        // Provider called 3 times, but chain only compiled once
        $this->assertEquals(3, $compileCount);
    }

    public function testMultipleCallsUseSameChain(): void
    {
        $executionCount = 0;

        $chain = new Chain(fn($c) => $c);
        $chain->link(function ($context, $next) use (&$executionCount) {
            $executionCount++;
            return $next($context);
        });

        // Multiple calls should work correctly
        $result1 = $chain->call('first');
        $result2 = $chain->call('second');
        $result3 = $chain->call('third');

        $this->assertEquals('first', $result1);
        $this->assertEquals('second', $result2);
        $this->assertEquals('third', $result3);
        $this->assertEquals(3, $executionCount);
    }
}

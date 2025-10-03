<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chain supports memoization to cache results for identical inputs
 */
#[Group('middleware')]
#[Group('chain')]
class MemoizationCachesResultsTest extends TestCase
{
    public function testMemoizationCachesResults(): void
    {
        $calls = 0;
        $middleware = function ($context, $next) use (&$calls) {
            $calls++;
            return $next($context);
        };

        $chain = new Chain(fn($c) => "result:$c", [$middleware]);
        $chain = $chain->memoize();

        // First call
        $result1 = $chain->call('test');
        $this->assertEquals(1, $calls);

        // Second call with same input - should use cache
        $result2 = $chain->call('test');
        $this->assertEquals(1, $calls, 'Middleware should not be called again for memoized input');

        $this->assertEquals($result1, $result2);
    }

    public function testMemoizationWithDifferentInputs(): void
    {
        $calls = 0;

        $chain = new Chain(
            function ($c) use (&$calls) {
                $calls++;
                return "result:$c";
            }
        );
        $chain = $chain->memoize();

        $chain->call('input1');
        $this->assertEquals(1, $calls);

        $chain->call('input2');
        $this->assertEquals(2, $calls, 'Different input should not use cache');

        // Memoization only caches the last call, so calling 'input1' again will execute again
        $chain->call('input1');
        $this->assertEquals(3, $calls, 'Memoization only caches the most recent call');

        // But calling with the same input immediately should use cache
        $chain->call('input1');
        $this->assertEquals(3, $calls, 'Repeated input should use cache');
    }

    public function testMemoizationReturnsCachedResult(): void
    {
        $counter = 0;
        $chain = new Chain(function ($c) use (&$counter) {
            return "result:" . ($counter++);
        });
        $chain = $chain->memoize();

        $result1 = $chain->call('test');
        $result2 = $chain->call('test');

        $this->assertEquals($result1, $result2);
        $this->assertEquals('result:0', $result1);
        $this->assertEquals('result:0', $result2);
    }
}
